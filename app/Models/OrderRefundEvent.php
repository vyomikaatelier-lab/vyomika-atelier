<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OrderRefundEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_refund_id',
        'event',
        'from_status',
        'to_status',
        'gateway_refund_id',
        'amount_paise',
        'payload_sha256',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Refund event records cannot be changed.');
        }

        return parent::save($options);
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Refund event records cannot be changed.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('Refund event records cannot be deleted.');
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(OrderRefund::class, 'order_refund_id');
    }
}
