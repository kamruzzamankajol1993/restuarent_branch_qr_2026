<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $guarded = [];

    // QR application never generates official human-readable Order/KOT/Invoice sequences.
    // Restaurant Management is the schema/numbering authority. QR only creates a stable
    // QR reference and inherits branch ownership from the scanned table.
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (Schema::hasColumn('orders', 'feedback_token') && empty($order->feedback_token)) {
                $order->feedback_token = Str::random(48);
            }

            if (Schema::hasColumn('orders', 'branch_id') && !$order->branch_id) {
                $tableBranchId = $order->table_id
                    ? \App\Models\Table::query()->whereKey($order->table_id)->value('branch_id')
                    : null;

                $order->branch_id = $tableBranchId;
                if (!$order->branch_id) {
                    throw new \RuntimeException('QR orders require a branch-owned table.');
                }
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

    public function ensureFeedbackToken(): string
    {
        if (!Schema::hasColumn($this->getTable(), 'feedback_token')) {
            return '';
        }

        if (empty($this->feedback_token)) {
            do {
                $token = Str::random(48);
            } while (self::query()->where('feedback_token', $token)->exists());

            $this->forceFill(['feedback_token' => $token])->saveQuietly();
        }

        return (string) $this->feedback_token;
    }
}
