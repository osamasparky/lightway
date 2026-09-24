<?php

namespace App\Http\Controllers\Web;
use App\Mixins\RegistrationPackage\SubscribeMixins;

use App\Http\Controllers\Controller;
use App\Models\Accounting;
use App\Models\Bundle;
use App\Models\Sale;
use App\Models\Subscribe;
use App\Models\SubscribeUse;
use App\Models\UserAccess;
use App\Models\Webinar;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SubscribeController extends Controller
{
    public function apply(Request $request, $webinarSlug)
    {
        $webinar = Webinar::where('slug', $webinarSlug)
            ->where('status', 'active')
            ->where('subscribe', true)
            ->first();

        if (!empty($webinar)) {
            return $this->handleSale($webinar, 'webinar_id');
        }

        abort(404);
    }

    public function bundleApply($bundleSlug)
    {
        $bundle = Bundle::where('slug', $bundleSlug)
            ->where('subscribe', true)
            ->first();

        if (!empty($bundle)) {
            return $this->handleSale($bundle, 'bundle_id');
        }

        abort(404);
    }

    private function handleSale($item, $itemName = 'webinar_id')
    {
        if (auth()->check()) {
            $user = auth()->user();

            $subscribe = Subscribe::getActiveSubscribe($user->id);

            if (!$subscribe) {
                $toastData = [
                    'title' => trans('public.request_failed'),
                    'msg' => trans('site.you_dont_have_active_subscribe'),
                    'status' => 'error'
                ];
                return back()->with(['toast' => $toastData]);
            }
            $subscribeMixins = new SubscribeMixins();

            $allowedItem = $subscribeMixins
                ->handleTargetProductLimitationOnCourseQuery(
                    $subscribe,
                    $item instanceof Builder ? $item : $item->newQuery()->where('id', $item->id),
                    $itemName == 'webinar_id' ? 'courses' : 'bundles'
                )
                ->first();

            if (!$allowedItem) {
                $toastData = [
                    'title' => trans('public.request_failed'),
                    'msg' => trans('update.you_cannot_purchase_this_item_with_your_subscription_plan'),
                    'status' => 'error'
                ];

                return back()->with(['toast' => $toastData]);
            }

            $checkCourseForSale = checkCourseForSale($item, $user);

            if ($checkCourseForSale != 'ok') {
                return $checkCourseForSale;
            }

            $sale = Sale::create([
                'buyer_id' => $user->id,
                'seller_id' => $item->creator_id,
                $itemName => $item->id,
                'subscribe_id' => $subscribe->id,
                'type' => $itemName == 'webinar_id' ? Sale::$webinar : Sale::$bundle,
                'payment_method' => Sale::$subscribe,
                'amount' => 0,
                'total_amount' => 0,
                'created_at' => time(),
            ]);

            $subscriptionAccess = UserAccess::where('user_id', $user->id)
            ->where('accessible_type', 'subscribe')
            ->where('accessible_id', $subscribe->id)
            ->first();

            if ($subscriptionAccess) {

                UserAccess::create([
                    'sale_id' => $sale->id,
                    'buyer_id' => $subscriptionAccess->buyer_id,
                    'user_id' => $user->id,
                    'subscribe_id' => $subscribe->id,
                    'accessible_type' => $itemName == 'webinar_id'
                        ? 'webinar'
                        : 'bundle',
                    'accessible_id' => $item->id,
                    'source_type' => 'subscription',
                    'created_at' => time(),
                ]);
            }

            Accounting::createAccountingForSaleWithSubscribe($item, $subscribe, $itemName);

            SubscribeUse::create([
                'user_id' => $user->id,
                'subscribe_id' => $subscribe->id,
                $itemName => $item->id,
                'sale_id' => $sale->id,
                'installment_order_id' => $subscribe->installment_order_id ?? null,
            ]);

            $toastData = [
                'title' => trans('cart.success_pay_title'),
                'msg' => trans('cart.success_pay_msg_subscribe'),
                'status' => 'success'
            ];
            return back()->with(['toast' => $toastData]);
        } else {
            return redirect('/login');
        }
    }
}
