<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    use HasFactory;

    protected $guarded = [];

    // রিলেশনশিপ
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function kot()
{
    return $this->belongsTo(OrderKot::class, 'order_kot_id');
}
}
