<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'name', 'slug', 'summary', 'content', 'image',
        'has_calculator', 'has_designs', 'lead_form', 'rate_per_sqft',
        'is_active', 'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'has_calculator' => 'boolean',
            'has_designs' => 'boolean',
            'is_active' => 'boolean',
            'rate_per_sqft' => 'decimal:2',
        ];
    }

    public function designs(): HasMany
    {
        return $this->hasMany(ServiceDesign::class);
    }

    public function usesPopupForm(): bool
    {
        return $this->lead_form === 'popup';
    }
}
