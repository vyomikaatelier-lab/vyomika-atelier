<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use LogicException;

/**
 * Immutable evidence that a signature-valid Razorpay payment webhook was
 * processed. The body and signature are not stored. A repeated delivery of
 * the same body keeps the first outcome and does not insert another row.
 */
class PaymentWebhookReceipt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'event',
        'payload_sha256',
        'razorpay_order_id',
        'razorpay_payment_id',
        'outcome',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    public static function record(
        string $payloadSha256,
        string $event,
        ?int $orderId,
        ?string $razorpayOrderId,
        ?string $paymentId,
        string $outcome,
    ): void {
        if (self::query()->where('payload_sha256', $payloadSha256)->exists()) {
            return;
        }

        try {
            self::query()->create([
                'order_id' => $orderId,
                'event' => $event,
                'payload_sha256' => $payloadSha256,
                'razorpay_order_id' => $razorpayOrderId,
                'razorpay_payment_id' => $paymentId,
                'outcome' => $outcome,
                'received_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent delivery of the same signed body already recorded it.
        }
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Payment webhook receipts cannot be changed.');
        }

        return parent::save($options);
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Payment webhook receipts cannot be changed.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('Payment webhook receipts cannot be deleted.');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
