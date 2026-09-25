<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OrderRefundLine extends Model
{
    public const STOCK_PENDING = 'pending';

    public const STOCK_RESTORED = 'restored';

    public const STOCK_SKIPPED = 'skipped';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'amount_paise' => 'integer',
            'stock_restored_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (OrderRefundLine $line) {
            foreach (['order_refund_id', 'order_item_id', 'quantity', 'amount_paise'] as $field) {
                if ($line->isDirty($field)) {
                    throw new LogicException('Refund line amounts cannot be changed.');
                }
            }

            if (! $line->isDirty('stock_restoration')) {
                return;
            }

            $from = (string) $line->getOriginal('stock_restoration');
            $to = (string) $line->stock_restoration;

            if ($from !== self::STOCK_PENDING || ! in_array($to, [self::STOCK_RESTORED, self::STOCK_SKIPPED], true)) {
                throw new LogicException('Stock restoration can only move once from pending.');
            }
        });
    }

    public function delete(): ?bool
    {
        throw new LogicException('Refund lines cannot be deleted.');
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(OrderRefund::class, 'order_refund_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
