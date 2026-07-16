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
        'address_line1',
        'address_line2',
        'city',
        'state',
        'pincode',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function formatted(): string
    {
        $decoded = self::decodeLine2($this->address_line2);

        $parts = array_filter([
            $this->address_line1,
            $decoded['company'] ?: null,
            $this->city,
            $this->state,
            $this->pincode,
            $decoded['country'] !== 'India' ? $decoded['country'] : null,
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
