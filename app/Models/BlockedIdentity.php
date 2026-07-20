<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedIdentity extends Model
{
    protected $fillable = [
        'identity_type',
        'value_hash',
        'value_hint',
        'email_domain',
        'message_pattern',
        'reason',
        'blocked_by',
        'lead_id',
        'is_active',
        'expires_at',
        'lifted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'lifted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public static function hashValue(string $value): string
    {
        return hash('sha256', strtolower(trim($value)));
    }

    public static function hint(string $value): string
    {
        $value = trim($value);
        if (strlen($value) <= 4) {
            return '****';
        }

        return substr($value, 0, 2) . str_repeat('*', max(2, strlen($value) - 4)) . substr($value, -2);
    }
}
