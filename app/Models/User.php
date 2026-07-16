<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ACCOUNT_TYPES = [
        'customer' => 'Customer',
        'interior_designer' => 'Interior Designer',
        'architect' => 'Architect',
        'contractor' => 'Contractor',
        'dealer' => 'Dealer',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'mobile_country_code',
        'mobile',
        'whatsapp',
        'city',
        'account_type',
        'phone_verified_at',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    public function isCustomer(): bool
    {
        return ! $this->is_admin;
    }

    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function mobileE164(): string
    {
        $code = preg_replace('/\D/', '', $this->mobile_country_code ?? '91');
        $mobile = preg_replace('/\D/', '', $this->mobile ?? '');

        return $code . $mobile;
    }

    public function accountTypeLabel(): string
    {
        return self::ACCOUNT_TYPES[$this->account_type] ?? ucfirst(str_replace('_', ' ', $this->account_type ?? 'customer'));
    }
}
