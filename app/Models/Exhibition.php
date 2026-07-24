<?php

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;

class Exhibition extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'city',
        'country',
        'year',
        'description',
        'cover_image',
        'gallery',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function locationLabel(): string
    {
        $parts = array_filter([$this->city, $this->country !== 'India' ? $this->country : null]);

        return implode(', ', $parts) ?: ($this->city ?? '');
    }

    public function coverImageUrl(): ?string
    {
        return MediaUrl::resolve($this->cover_image);
    }

    /** @return array<int, string> */
    public function galleryUrls(): array
    {
        return MediaUrl::resolveMany($this->gallery);
    }
}
