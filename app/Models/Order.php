<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $guarded = [];

    // ইনভয়েস সেটিং অনুযায়ী অর্ডার আইডি জেনারেট করার লজিক
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            // Phase 1 compatibility: Restaurant Management owns the shared branch schema.
            // Until the full QR branch-routing phase is deployed, every QR order inherits
            // its branch from the scanned table (or Main Branch as a safe single-mode fallback).
            if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'branch_id') && !$order->branch_id) {
                $tableBranchId = $order->table_id
                    ? \App\Models\Table::query()->whereKey($order->table_id)->value('branch_id')
                    : null;

                $order->branch_id = $tableBranchId
                    ?: (\Illuminate\Support\Facades\Schema::hasTable('branches')
                        ? \App\Models\Branch::query()->where('is_main', true)->value('id')
                        : null);
            }

            // ডাটাবেজ থেকে ইনভয়েস সেটিং নিয়ে আসা
            $invoiceSetting = \App\Models\InvoiceSetting::first();

            // যদি সেটিংসে starting_number থাকে তবে সেটি নিবে, নাহলে ডিফল্ট 1001 থেকে শুরু হবে
            $startingNumber = $invoiceSetting && $invoiceSetting->starting_number ? $invoiceSetting->starting_number : 1001;

            // ডাটাবেজের সর্বশেষ অর্ডারটি বের করা (যাতে আমরা সর্বোচ্চ নম্বরটি পাই)
            $lastOrder = self::orderBy('id', 'desc')->first();

            if ($lastOrder && $lastOrder->order_number >= $startingNumber) {
                // যদি আগে কোনো অর্ডার থাকে, তবে সর্বশেষ অর্ডারের নাম্বারের সাথে ১ যোগ হবে
                $order->order_number = $lastOrder->order_number + 1;
            } else {
                // যদি এটিই প্রথম অর্ডার হয় অথবা আগের নাম্বার starting_number এর চেয়ে ছোট হয়
                $order->order_number = $startingNumber;
            }
        });
    }

    // ==========================================
    // রিলেশনশিপস (Relationships)
    // ==========================================

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function kots()
    {
        return $this->hasMany(OrderKot::class);
    }

    public function waiter()
    {
        // এটি Waiter মডেলের সাথে কানেক্ট করবে
        return $this->belongsTo(Waiter::class, 'waiter_id');
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }
}
