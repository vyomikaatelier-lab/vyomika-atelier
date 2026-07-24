<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'order_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'alt_mobile',
        'shipping_address',
        'city',
        'state',
        'pincode',
        'country',
        'subtotal',
        'shipping_cost',
        'total',
        'status',
        'payment_method',
        'payment_id',
        'razorpay_order_id',
        'notes',
        'admin_notes',
        'shipping_snapshot',
        'billing_snapshot',
        'checkout_token',
        'expires_at',
        'stock_deducted_at',
        'order_received_email_sent_at',
        'payment_email_sent_at',
        'admin_order_notified_at',
        'admin_payment_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'total' => 'decimal:2',
            'shipping_snapshot' => 'array',
            'billing_snapshot' => 'array',
            'expires_at' => 'datetime',
            'stock_deducted_at' => 'datetime',
            'order_received_email_sent_at' => 'datetime',
            'payment_email_sent_at' => 'datetime',
            'admin_order_notified_at' => 'datetime',
            'admin_payment_notified_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generateOrderNumber(): string
    {
        return 'VA-' . strtoupper(substr(uniqid(), -8));
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'paid' => 'Paid',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isExpired(): bool
    {
        return $this->isPending()
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public static function pendingExpiryHours(): int
    {
        return max(1, (int) config('orders.pending_expiry_hours', 24));
    }
}
