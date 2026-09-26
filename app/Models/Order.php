<?php

namespace App\Models;

use App\Services\RefundMoney;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generateOrderNumber(): string
    {
        return 'VA-'.strtoupper(substr(uniqid(), -8));
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
        return $this->hasReconciliationEvidence();
    }

    /**
     * Fail closed for any reconciliation evidence.
     *
     * Clean values are null, an empty array, an empty object, false, zero,
     * and an empty string, including those values nested inside otherwise
     * empty structures. A non-empty extra_payment_ids or
     * conflicting_payment_ids collection is evidence even when its elements
     * look empty. Any other non-empty value must be investigated.
     */
    public function hasReconciliationEvidence(): bool
    {
        if ($this->isReconciliationRequired() || filled($this->reconciliation_reason)) {
            return true;
        }

        return self::reconciliationValueHasEvidence($this->reconciliation_meta);
    }

    public static function reconciliationValueHasEvidence(mixed $value, ?string $key = null): bool
    {
        if ($value instanceof \stdClass) {
            $value = (array) $value;
        }

        if (self::isReservedPaymentCollection($key, $value)) {
            return true;
        }

        if ($value === null || $value === false || $value === '' || $value === []) {
            return false;
        }

        if (is_array($value)) {
            foreach ($value as $nestedKey => $nested) {
                $childKey = is_string($nestedKey) ? $nestedKey : null;

                if (self::reconciliationValueHasEvidence($nested, $childKey)) {
                    return true;
                }
            }

            return false;
        }

        if ($value === true) {
            return true;
        }

        if (is_int($value) || is_float($value)) {
            return $value !== 0 && $value !== 0.0;
        }

        if (is_string($value)) {
            return true;
        }

        return true;
    }

    private static function isReservedPaymentCollection(?string $key, mixed $value): bool
    {
        if (! in_array($key, ['extra_payment_ids', 'conflicting_payment_ids'], true)) {
            return false;
        }

        if ($value instanceof \stdClass) {
            $value = (array) $value;
        }

        return is_array($value) && $value !== [];
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

    /**
     * Stored gateway payment id. Fulfilment status is not captured payment.
     */
    public function hasDurableCapturedPaymentEvidence(): bool
    {
        return filled($this->payment_id);
    }

    public function paymentAwareStatusLabel(): string
    {
        if (
            $this->payment_method === 'razorpay'
            && ! $this->hasDurableCapturedPaymentEvidence()
            && ! $this->needsPaymentReview()
            && $this->isFulfilled()
        ) {
            return 'Payment not confirmed';
        }

        return $this->statusLabel();
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
        $processed = RefundMoney::formatRupees((int) $this->refunded_amount_paise);
        $pending = RefundMoney::formatRupees((int) $this->refund_pending_amount_paise);

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
        if ($this->needsPaymentReview()) {
            return $this->statusLabel();
        }

        if (
            $this->payment_method === 'razorpay'
            && ! $this->hasDurableCapturedPaymentEvidence()
            && ! $this->needsPaymentReview()
            && $this->isFulfilled()
        ) {
            return 'Payment not confirmed';
        }

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

        if ($this->payment_method === 'razorpay' && ! $this->hasDurableCapturedPaymentEvidence()) {
            return ['pending', 'cancelled'];
        }

        if ($this->hasDurableCapturedPaymentEvidence() || ($this->payment_method !== 'razorpay' && $this->hasCapturedPayment())) {
            $options = ['paid', 'processing', 'shipped', 'delivered'];

            if (! in_array($this->status, $options, true)) {
                array_unshift($options, (string) $this->status);
            }

            return $options;
        }

        return ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled'];
    }

    /**
     * Razorpay confirmation copy requires a stored payment id.
     * Historical non-Razorpay rows keep their stored fulfilment wording.
     */
    public function showsCapturedPaymentConfirmation(): bool
    {
        if ($this->payment_method === 'razorpay') {
            return filled($this->payment_id);
        }

        return filled($this->payment_id) || $this->isFulfilled();
    }

    /**
     * Paid or later fulfilment states.
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
