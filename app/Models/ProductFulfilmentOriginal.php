<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductFulfilmentOriginal extends Model
{
    protected $fillable = [
        'product_id',
        'original',
        'applied',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'original' => 'array',
            'applied' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
