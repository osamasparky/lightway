<?php

namespace App\Services\Payment;

use App\Models\OrderItem;
use App\Models\Sale;
use App\Models\Subscribe;
use App\Models\TempUser;
use App\User;

class PaymentService
{
  public function handleGiftPurchases($order, $saleTypes, $giftUsers)
  {
    $emails = [];
    $selfWebinars = [];
    $insertData = [];

    foreach ($saleTypes as $itemId => $type) {

      $orderItem = OrderItem::where('id', $itemId)
        ->where('order_id', $order->id)
        ->first();

      if (!$orderItem) {
        continue;
      }

      if ($type === 'other') {

        $email = strtolower(trim($giftUsers[$itemId]['email'] ?? ''));

        if (in_array($email, $emails)) {
          throw new \Exception('Please make all gift emails different.');
        }

        $emails[] = $email;

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {

          $alreadyBought = Sale::query()
            ->where(function ($query) use ($existingUser) {

              $query->where('buyer_id', $existingUser->id)
                ->orWhere('receiver_id', $existingUser->id);
            })
            ->whereNull('refund_at')
            ->where(function ($query) use ($orderItem) {

              if (!empty($orderItem->webinar_id)) {
                $query->where('webinar_id', $orderItem->webinar_id);
              } elseif (!empty($orderItem->bundle_id)) {
                $query->where('bundle_id', $orderItem->bundle_id);
              } elseif (!empty($orderItem->subscribe_id)) {
                $query->where('subscribe_id', $orderItem->subscribe_id);
              }
            })
            ->exists();

          if ($alreadyBought) {
            throw new \Exception($email . ' already owns this item.');
          }
        }
      }

      if ($type === 'self') {

        $itemKey = null;

        if (!empty($orderItem->webinar_id)) {
          $itemKey = 'webinar_' . $orderItem->webinar_id;
        } elseif (!empty($orderItem->bundle_id)) {
          $itemKey = 'bundle_' . $orderItem->bundle_id;
        } elseif (!empty($orderItem->subscribe_id)) {
          $itemKey = 'subscribe_' . $orderItem->subscribe_id;
        }

        // Check if user already bought this item for himself
        $alreadyBought = false;

        if (
            !empty($orderItem->webinar_id) ||
            !empty($orderItem->bundle_id) ||
            !empty($orderItem->subscribe_id)
        ) {

            $alreadyBought = Sale::query()
                ->where('buyer_id', $order->user_id)
                ->whereNull('receiver_id')
                ->whereNull('refund_at')
                ->where(function ($query) use ($orderItem) {

                    if (!empty($orderItem->webinar_id)) {
                        $query->where('webinar_id', $orderItem->webinar_id);
                    } elseif (!empty($orderItem->bundle_id)) {
                        $query->where('bundle_id', $orderItem->bundle_id);
                    } elseif (!empty($orderItem->subscribe_id)) {
                        $query->where('subscribe_id', $orderItem->subscribe_id);
                    }
                })
                ->exists();

            if ($alreadyBought) {
                throw new \Exception(
                    'You already bought this item for yourself before.'
                );
            }
        }

        // Check if user already has an active subscription plan
        if (!empty($orderItem->subscribe_id)) {
          $activeSubscribe = Subscribe::getActiveSubscribe($order->user_id);

          if ($activeSubscribe) {
            throw new \Exception('You cannot purchase a new subscription plan as you already have an active plan.');
          }
        }

        if ($itemKey && in_array($itemKey, $selfWebinars)) {

          throw new \Exception(
            'You cannot purchase the same item for yourself more than once.'
          );
        }

        if ($itemKey) {
          $selfWebinars[] = $itemKey;
        }

        continue;
      }

      $gift = $giftUsers[$itemId] ?? null;

      $insertData[] = [
        'buyer_id' => $order->user_id,
        'order_id' => $order->id,
        'order_item_id' => $orderItem->id,
        'full_name' => $gift['full_name'],
        'email' => $gift['email'],
        'password' => bcrypt($gift['password']),
        'created_at' => time(),
        'updated_at' => time(),
      ];
    }

    if (!empty($insertData)) {
      TempUser::insert($insertData);
    }
  }
}
