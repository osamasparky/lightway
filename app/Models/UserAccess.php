<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\User;

class UserAccess extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'sale_id',
        'buyer_id',
        'user_id',
        'subscribe_id',
        'accessible_type',
        'accessible_id',
        'source_type',
        'created_at'
    ];

    public function receiver()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function accessible()
    {
        return $this->morphTo();
    }
}