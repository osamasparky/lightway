<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\traits\PaymentsTrait;
use App\Mixins\Cashback\CashbackAccounting;
use App\Models\Accounting;
use App\Models\BecomeInstructor;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentChannel;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ReserveMeeting;
use App\Models\Reward;
use App\Models\RewardAccounting;
use App\Models\Sale;
use App\Models\TicketUser;
use App\Services\Payment\PaymentService;
use App\PaymentChannels\ChannelManager;
use App\Http\Requests\PaymentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

class PaymentController extends Controller
{
    use PaymentsTrait;


    protected $order_session_key = 'payment.order_id';

    public function paymentRequest(PaymentRequest $request, PaymentService $paymentService)
    {

        $user = auth()->user();
        $gateway = $request->input('gateway');
        $orderId = $request->input('order_id');

        $order = Order::where('id', $orderId)
            ->where('user_id', $user->id)
            ->first();

        if ($order->type === Order::$meeting) {
            $orderItem = OrderItem::where('order_id', $order->id)->first();
            $reserveMeeting = ReserveMeeting::where('id', $orderItem->reserve_meeting_id)->first();
            $reserveMeeting->update(['locked_at' => time()]);
        }
        try {

            $paymentService->handleGiftPurchases(
                $order,
                $request->sale_type,
                $request->gift_user ?? []
            );

        } catch (\Exception $e) {

            return back()->with([
                'toast' => [
                    'title' => trans('public.request_failed'),
                    'msg' => $e->getMessage(),
                    'status' => 'error'
                ]
            ]);
        }

        if ($gateway === 'credit') {

            if ($user->getAccountingCharge() < $order->total_amount) {
                $order->update(['status' => Order::$fail]);

                session()->put($this->order_session_key, $order->id);

                return redirect('/payments/status');
            }

            $order->update([
                'payment_method' => Order::$credit
            ]);

            $this->setPaymentAccounting($order, 'credit');

            $order->update([
                'status' => Order::$paid
            ]);

            session()->put($this->order_session_key, $order->id);

            return redirect('/payments/status');
        }

        $paymentChannel = PaymentChannel::where('id', $gateway)
            ->where('status', 'active')
            ->first();

        if (!$paymentChannel) {
            $toastData = [
                'title' => trans('cart.fail_purchase'),
                'msg' => trans('public.channel_payment_disabled'),
                'status' => 'error'
            ];
            return back()->with(['toast' => $toastData]);
        }

        $order->payment_method = Order::$paymentChannel;
        $order->save();

        try {
            $channelManager = ChannelManager::makeChannel($paymentChannel);
            $redirect_url = $channelManager->paymentRequest($order);

            if (in_array($paymentChannel->class_name, PaymentChannel::$gatewayIgnoreRedirect)) {
                return $redirect_url;
            }

            return Redirect::away($redirect_url);

        } catch (\Exception $exception) {
            //dd($exception->getMessage());

            $toastData = [
                'title' => trans('cart.fail_purchase'),
                'msg' => trans('cart.gateway_error'),
                'status' => 'error'
            ];
            return back()->with(['toast' => $toastData]);
        }
    }

    /**
     * Dry run of paymentRequest() for the one-stop checkout page: validates the recipient
     * choices (same rules and gift checks) and rolls everything back, so the buyer sees
     * problems next to the form instead of being sent back to a POST-only page.
     */
    public function checkoutCheck(PaymentRequest $request, PaymentService $paymentService)
    {
        $user = auth()->user();

        $order = Order::where('id', $request->input('order_id'))
            ->where('user_id', $user->id)
            ->where('status', Order::$pending)
            ->first();

        if (empty($order)) {
            return response()->json(['message' => trans('home.lw_co_err_generic')], 422);
        }

        $giftUsers = $request->input('gift_user', []);
        $errors = [];
        $emails = [];

        foreach ($request->input('sale_type', []) as $itemId => $type) {
            if ($type !== 'other') {
                continue;
            }

            $email = strtolower(trim($giftUsers[$itemId]['email'] ?? ''));

            if ($email == strtolower($user->email)) {
                $errors["gift_user.$itemId.email"] = trans('home.lw_co_err_self_email');
            } elseif (in_array($email, $emails)) {
                $errors["gift_user.$itemId.email"] = trans('home.lw_co_err_same_email');
            }

            $emails[] = $email;
        }

        if (empty($errors)) {
            DB::beginTransaction();

            try {
                $paymentService->handleGiftPurchases($order, $request->input('sale_type'), $giftUsers);
            } catch (\Exception $e) {
                $message = $e->getMessage();

                if (str_contains($message, 'already owns this item')) {
                    $message = trans('home.lw_co_err_owned', ['email' => trim(str_replace('already owns this item.', '', $message))]);
                } elseif (str_contains($message, 'bought this item for yourself') or str_contains($message, 'same item for yourself')) {
                    $message = trans('home.lw_co_err_self_owned');
                } elseif (str_contains($message, 'active plan')) {
                    $message = trans('home.lw_co_err_plan');
                } elseif (str_contains($message, 'gift emails different')) {
                    $message = trans('home.lw_co_err_same_email');
                }

                $errors['general'] = $message;
            } finally {
                DB::rollBack();
            }
        }

        if (!empty($errors)) {
            return response()->json(['message' => reset($errors), 'errors' => $errors], 422);
        }

        return response()->json(['code' => 200]);
    }

    public function paymentVerify(Request $request, $gateway)
    {
        $paymentChannel = PaymentChannel::where('class_name', $gateway)
            ->where('status', 'active')
            ->first();

        try {
            $channelManager = ChannelManager::makeChannel($paymentChannel);
            $order = $channelManager->verify($request);

            return $this->paymentOrderAfterVerify($order);

        } catch (\Exception $exception) {
            $toastData = [
                'title' => trans('cart.fail_purchase'),
                'msg' => trans('cart.gateway_error'),
                'status' => 'error'
            ];
            return redirect('cart')->with(['toast' => $toastData]);
        }
    }


    private function paymentOrderAfterVerify($order)
    {
        if (!empty($order)) {
            DB::transaction(function () use ($order) {
                $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

                if (!empty($lockedOrder) && $lockedOrder->status == Order::$paying) {
                    $this->setPaymentAccounting($lockedOrder);
                    $lockedOrder->update(['status' => Order::$paid]);
                } else if (!empty($lockedOrder)) {
                    if ($lockedOrder->type === Order::$meeting) {
                        $orderItem = OrderItem::where('order_id', $lockedOrder->id)->first();

                        if ($orderItem && $orderItem->reserve_meeting_id) {
                            $reserveMeeting = ReserveMeeting::where('id', $orderItem->reserve_meeting_id)->first();

                            if ($reserveMeeting) {
                                $reserveMeeting->update(['locked_at' => null]);
                            }
                        }
                    }
                }
            });

            session()->put($this->order_session_key, $order->id);

            return redirect("/payments/status?t={$order->id}");
        } else {
            $toastData = [
                'title' => trans('cart.fail_purchase'),
                'msg' => trans('cart.gateway_error'),
                'status' => 'error'
            ];

            return redirect('cart')->with($toastData);
        }
    }

    public function setPaymentAccounting($order, $type = null)
    {
        DB::transaction(function () use ($order, $type) {
            $cashbackAccounting = new CashbackAccounting();

            if ($order->is_charge_account) {
                Accounting::charge($order);

                $cashbackAccounting->rechargeWallet($order);
            } else {
                foreach ($order->orderItems as $orderItem) {
                    $updateInstallmentOrderAfterSale = false;
                    $updateProductOrderAfterSale = false;

                    if (!empty($orderItem->gift_id)) {
                        $gift = $orderItem->gift;

                        $gift->update([
                            'status' => 'active'
                        ]);

                        $gift->sendNotificationsWhenActivated($orderItem->total_amount);
                    }

                    if (!empty($orderItem->subscribe_id)) {
                        Accounting::createAccountingForSubscribe($orderItem, $type);
                    } elseif (!empty($orderItem->promotion_id)) {
                        Accounting::createAccountingForPromotion($orderItem, $type);
                    } elseif (!empty($orderItem->registration_package_id)) {
                        Accounting::createAccountingForRegistrationPackage($orderItem, $type);

                        if (!empty($orderItem->become_instructor_id)) {
                            BecomeInstructor::where('id', $orderItem->become_instructor_id)
                                ->update([
                                    'package_id' => $orderItem->registration_package_id
                                ]);
                        }
                    } elseif (!empty($orderItem->installment_payment_id)) {
                        Accounting::createAccountingForInstallmentPayment($orderItem, $type);

                        $updateInstallmentOrderAfterSale = true;
                    } else {
                        // webinar and meeting and product and bundle

                        Accounting::createAccounting($orderItem, $type);
                        TicketUser::useTicket($orderItem);

                        if (!empty($orderItem->product_id)) {
                            $updateProductOrderAfterSale = true;
                        }
                    }

                    // Set Sale After All Accounting
                    $sale = Sale::createSales($orderItem, $order->payment_method);

                    if (!empty($orderItem->reserve_meeting_id)) {
                        $reserveMeeting = ReserveMeeting::where('id', $orderItem->reserve_meeting_id)->first();
                        $reserveMeeting->update([
                            'sale_id' => $sale->id,
                            'reserved_at' => time()
                        ]);

                        $reserver = $reserveMeeting->user;

                        if ($reserver) {
                            $this->handleMeetingReserveReward($reserver);
                        }
                    }

                    if ($updateInstallmentOrderAfterSale) {
                        $this->updateInstallmentOrder($orderItem, $sale);
                    }

                    if ($updateProductOrderAfterSale) {
                        $this->updateProductOrder($sale, $orderItem);
                    }
                }

                // Set Cashback Accounting For All Order Items
                $cashbackAccounting->setAccountingForOrderItems($order->orderItems);
            }

            Cart::emptyCart($order->user_id);
        });
    }

    public function payStatus(Request $request)
    {
        $orderId = $request->get('t', null);

        if (!empty(session()->get($this->order_session_key, null))) {
            $orderId = session()->get($this->order_session_key, null);
            session()->forget($this->order_session_key);
        }

        $authId = auth()->id();

        $order = Order::where('id', $orderId)
            ->where('user_id', $authId)
            ->first();

        if (!empty($order)) {
            $data = [
                'pageTitle' => trans('public.cart_page_title'),
                'order' => $order,
            ];

            return view('web.default.cart.status_pay', $data);
        }

        return redirect('/panel');
    }

}
