<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class OrderRefund extends Model
{
    public const STATUS_RESERVED = 'reserved';

    public const STATUS_SUBMIT_UNCERTAIN = 'submit_uncertain';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    public const REASONS = [
        'customer_request',
        'production_not_started',
        'goodwill',
        'duplicate_payment',
        'other',
    ];

    public const OPEN_STATUSES = [
        self::STATUS_RESERVED,
        self::STATUS_SUBMIT_UNCERTAIN,
        self::STATUS_PENDING,
    ];

    public const COMMITTED_STATUSES = [
        self::STATUS_RESERVED,
        self::STATUS_SUBMIT_UNCERTAIN,
        self::STATUS_PENDING,
        self::STATUS_PROCESSED,
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'shipping_amount_paise' => 'integer',
            'includes_shipping' => 'boolean',
            'submit_attempts' => 'integer',
            'requested_at' => 'datetime',
            'submitted_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (OrderRefund $refund) {
            foreach ([
                'order_id',
                'payment_id',
                'idempotency_key',
                'receipt',
                'amount_paise',
                'currency',
                'kind',
                'reason_code',
                'includes_shipping',
                'shipping_amount_paise',
                'actor_user_id',
                'actor_staff_id',
                'internal_note',
                'requested_at',
            ] as $field) {
                if ($refund->isDirty($field)) {
                    throw new LogicException('Refund ledger fields cannot be changed.');
                }
            }

            if ($refund->isDirty('request_body_sha256') && filled($refund->getOriginal('request_body_sha256'))) {
                throw new LogicException('Refund request body cannot be changed.');
            }

            $originalGatewayId = $refund->getOriginal('gateway_refund_id');
            if ($refund->isDirty('gateway_refund_id') && filled($originalGatewayId)
                && (string) $refund->gateway_refund_id !== (string) $originalGatewayId) {
                throw new LogicException('Gateway refund id cannot be replaced.');
            }
        });
    }

    public function delete(): ?bool
    {
        throw new LogicException('Refund ledger records cannot be deleted.');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderRefundLine::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderRefundEvent::class);
    }

    public function countsAgainstCapturedAmount(): bool
    {
        return in_array($this->status, self::COMMITTED_STATUSES, true);
    }
}
