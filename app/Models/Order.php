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
        'captured_amount_paise',
        'refunded_amount_paise',
        'refund_pending_amount_paise',
        'refund_status',
        'razorpay_order_id',
        'reconciliation_reason',
        'reconciliation_meta',
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
            'captured_amount_paise' => 'integer',
            'refunded_amount_paise' => 'integer',
            'refund_pending_amount_paise' => 'integer',
            'shipping_snapshot' => 'array',
            'billing_snapshot' => 'array',
            'expires_at' => 'datetime',
            'stock_deducted_at' => 'datetime',
            'order_received_email_sent_at' => 'datetime',
            'payment_email_sent_at' => 'datetime',
            'admin_order_notified_at' => 'datetime',
            'admin_payment_notified_at' => 'datetime',
            'reconciliation_meta' => 'array',
        ];
    }

    public const STATUS_RECONCILIATION_REQUIRED = 'reconciliation_required';

    public const REVIEW_HEADING = 'Payment received — order under review';

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(OrderRefund::class);
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
        if ($this->needsPaymentReview()) {
            return self::REVIEW_HEADING;
        }

        return match ($this->status) {
            'pending' => 'Pending',
            'paid' => 'Paid',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            self::STATUS_RECONCILIATION_REQUIRED => self::REVIEW_HEADING,
            default => ucfirst((string) $this->status),
        };
    }

    public function isReconciliationRequired(): bool
    {
        return $this->status === self::STATUS_RECONCILIATION_REQUIRED;
    }

    public function needsPaymentReview(): bool
    {
        if ($this->isReconciliationRequired() || filled($this->reconciliation_reason)) {
            return true;
        }

        $meta = $this->reconciliation_meta ?? [];

        return ($meta['extra_payment_ids'] ?? []) !== []
            || ($meta['conflicting_payment_ids'] ?? []) !== [];
    }

    public function reconciliationReasonLabel(): string
    {
        return match ($this->reconciliation_reason) {
            'insufficient_stock' => 'Stock could not be reserved',
            'stock_unverified' => 'Stock could not be verified',
            'captured_after_expiry' => 'Payment arrived after the payment session expired',
            'captured_after_cancel' => 'Payment arrived after the order was cancelled',
            'captured_after_close' => 'Payment arrived after the order was closed',
            'payment_id_conflict' => 'Payment reference is already linked to another order',
            'duplicate_capture' => 'An additional captured payment was reported',
            'no_line_items' => 'The order has no line items to fulfil',
            default => 'Payment needs review',
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

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isAwaitingPayment(): bool
    {
        return $this->isPending() && ! $this->isExpired();
    }

    public function hasCapturedPayment(): bool
    {
        return filled($this->payment_id)
            || $this->stock_deducted_at !== null
            || $this->isFulfilled();
    }

    public function canOfferRefund(): bool
    {
        return $this->payment_method === 'razorpay'
            && filled($this->payment_id)
            && filled($this->razorpay_order_id)
            && $this->isFulfilled()
            && ! $this->needsPaymentReview()
            && $this->refund_status !== 'refunded';
    }

    public function showsRefundedCancellation(): bool
    {
        return $this->isCancelled()
            && $this->refund_status === 'refunded'
            && (int) $this->refunded_amount_paise > 0;
    }

    public function customerRefundSummary(): ?string
    {
        $processed = \App\Services\RefundMoney::formatRupees((int) $this->refunded_amount_paise);
        $pending = \App\Services\RefundMoney::formatRupees((int) $this->refund_pending_amount_paise);

        return match ($this->refund_status) {
            'pending' => (int) $this->refunded_amount_paise > 0
                ? 'Your payment was received. ₹'.$processed.' has been refunded, and a further refund of ₹'.$pending.' is in progress.'
                : 'Your payment was received. A refund of ₹'.$pending.' is in progress.',
            'partial' => 'Your payment was received. ₹'.$processed.' has been refunded.',
            'refunded' => 'Your payment was received and fully refunded (₹'.$processed.').',
            'failed' => 'Your payment was received. The refund could not be completed.',
            default => null,
        };
    }

    public function customerStatusLabel(): string
    {
        $refund = $this->customerRefundSummary();
        $base = $this->statusLabel();

        if ($refund === null) {
            return $base;
        }

        if ($this->showsRefundedCancellation()) {
            return $refund;
        }

        return $base.' · '.$refund;
    }

    /**
     * @return list<string>
     */
    public function adminStatusOptions(): array
    {
        if ($this->needsPaymentReview()) {
            return [];
        }

        if ($this->refund_status === 'refunded') {
            return [(string) $this->status];
        }

        if ($this->hasCapturedPayment()) {
            $options = ['paid', 'processing', 'shipped', 'delivered'];

            if (! in_array($this->status, $options, true)) {
                array_unshift($options, (string) $this->status);
            }

            return $options;
        }

        return ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled'];
    }

    /**
     * Paid or later fulfilment states that may show confirmed/success UI.
     */
    public function isFulfilled(): bool
    {
        return in_array($this->status, ['paid', 'processing', 'shipped', 'delivered'], true);
    }

    public static function pendingExpiryHours(): int
    {
        return max(1, (int) config('orders.pending_expiry_hours', 24));
    }
}
