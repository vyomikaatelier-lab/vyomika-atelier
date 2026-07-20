<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'name',
        'phone',
        'alt_mobile',
        'email',
        'address_line1',
        'address_line2',
        'house_building',
        'street',
        'locality',
        'landmark',
        'city',
        'state',
        'pincode',
        'country',
        'address_type',
        'floor',
        'lift_available',
        'delivery_instructions',
        'billing_same_as_shipping',
        'pin_lookup_status',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'lift_available' => 'boolean',
            'billing_same_as_shipping' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function formatted(): string
    {
        $decoded = self::decodeLine2($this->address_line2);
        $country = $this->country ?: $decoded['country'];

        $parts = array_filter([
            $this->house_building ?: $this->address_line1,
            $this->street,
            $this->locality,
            $this->landmark ? 'Near ' . $this->landmark : null,
            $decoded['company'] ?: null,
            $this->city,
            $this->state,
            $this->pincode,
            $country !== 'India' ? $country : null,
        ]);

        return implode(', ', $parts);
    }

    /**
     * @return array{company: string, country: string}
     */
    public static function decodeLine2(?string $line2): array
    {
        $company = '';
        $country = 'India';

        if (! $line2) {
            return ['company' => $company, 'country' => $country];
        }

        if (str_contains($line2, ' | Country: ')) {
            [$company, $country] = explode(' | Country: ', $line2, 2);

            return ['company' => $company, 'country' => $country ?: 'India'];
        }

        if (str_starts_with($line2, 'Country: ')) {
            return ['company' => '', 'country' => substr($line2, 9) ?: 'India'];
        }

        return ['company' => $line2, 'country' => $country];
    }

    public static function encodeLine2(?string $company, ?string $country): ?string
    {
        $company = trim((string) $company);
        $country = trim((string) $country) ?: 'India';

        if ($company && $country !== 'India') {
            return $company . ' | Country: ' . $country;
        }

        if ($company) {
            return $company;
        }

        if ($country !== 'India') {
            return 'Country: ' . $country;
        }

        return null;
    }
}
