<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappOtpVerification extends Model
{
    protected $fillable = [
        'mobile_e164',
        'purpose',
        'otp_hash',
        'payload',
        'attempts',
        'send_count',
        'ip_address',
        'expires_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function hasAttemptsRemaining(): bool
    {
        return $this->attempts < (int) config('account.otp.max_verification_attempts', 5);
    }
}
