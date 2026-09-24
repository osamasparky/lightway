<?php

namespace App\Models;

use App\Models\Observers\OrderItemNumberObserver;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];


    protected static function boot()
    {
        parent::boot();

        OrderItem::observe(OrderItemNumberObserver::class);
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo('App\Models\Order', 'order_id', 'id');
    }
    
    //For order item title in payment page
    public function getTitleAttribute()
    {
        if (!empty($this->attributes['title'])) {
            return $this->attributes['title'];
        }

        if (!empty($this->webinar_id)) {
            return optional($this->webinar)->title;
        }

        if (!empty($this->bundle_id)) {
            return optional($this->bundle)->title;
        }

        if (!empty($this->subscribe_id)) {
            return optional($this->subscribe)->title;
        }

        if (!empty($this->product_id)) {
            return optional($this->product)->title;
        }

        if (!empty($this->promotion_id)) {
            return optional($this->promotion)->title;
        }

        if (!empty($this->registration_package_id)) {
            return optional($this->registrationPackage)->title;
        }

        if (!empty($this->reserve_meeting_id)) {
            return optional(optional($this->reserveMeeting)->meeting)->title;
        }

        return null;
    }

    public function webinar()
    {
        return $this->belongsTo('App\Models\Webinar', 'webinar_id', 'id');
    }

    public function bundle()
    {
        return $this->belongsTo('App\Models\Bundle', 'bundle_id', 'id');
    }

    public function subscribe()
    {
        return $this->belongsTo('App\Models\Subscribe', 'subscribe_id', 'id');
    }

    public function promotion()
    {
        return $this->belongsTo('App\Models\Promotion', 'promotion_id', 'id');
    }

    public function reserveMeeting()
    {
        return $this->belongsTo('App\Models\ReserveMeeting', 'reserve_meeting_id', 'id');
    }

    public function registrationPackage()
    {
        return $this->belongsTo('App\Models\RegistrationPackage', 'registration_package_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product', 'product_id', 'id');
    }

    public function productOrder()
    {
        return $this->belongsTo('App\Models\ProductOrder', 'product_order_id', 'id');
    }

    public function installmentPayment()
    {
        return $this->belongsTo(InstallmentOrderPayment::class, 'installment_payment_id', 'id');
    }

    public function ticket()
    {
        return $this->belongsTo('App\Models\Ticket', 'ticket_id', 'id');
    }

    public function gift()
    {
        return $this->belongsTo(Gift::class, 'gift_id', 'id');
    }


    public static function getSeller($orderItem)
    {
        $seller = null;

        if (!empty($orderItem->webinar_id) and empty($orderItem->promotion_id)) {
            $seller = $orderItem->webinar->creator_id;
        } elseif (!empty($orderItem->reserve_meeting_id)) {
            $seller = $orderItem->reserveMeeting->meeting->creator_id;
        } elseif (!empty($orderItem->product_id)) {
            $seller = $orderItem->product->creator_id;
        } elseif (!empty($orderItem->bundle_id)) {
            $seller = $orderItem->bundle->creator_id;
        }

        return $seller;
    }

}
