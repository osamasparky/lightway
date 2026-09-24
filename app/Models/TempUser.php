<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class TempUser extends Model
{
    use HasFactory;

    protected $table = 'temp_users';

    public $timestamps = true;

    protected $dateFormat = 'U';

    protected $fillable = [
        'buyer_id',
        'order_id',
        'order_item_id',
        'full_name',
        'email',
        'password',
    ];

    // =========================
    // RELATIONSHIPS
    // =========================

    // buyer (user who purchased)
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    // order
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    // order item
    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    // =========================
    // AUTO HASH PASSWORD
    // =========================

    public function setPasswordAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['password'] = Hash::make($value);
        }
    }
}