<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceDesign extends Model
{
    protected $fillable = [
        'service_id', 'name', 'slug', 'description', 'content', 'image', 'product_slug', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function resolvedProductSlug(): ?string
    {
        if (filled($this->product_slug)) {
            return $this->product_slug;
        }

        return match ($this->slug) {
            'wave-partition' => 'champagne-wave-partition',
            'fluted-panel' => 'veil-fluted-panel',
            'laser-cut-screen' => 'laser-cut-partition',
            'frameless-glass-metal' => 'rose-gold-room-divider',
            default => null,
        };
    }

    public function galleryHref(?Service $service = null): ?string
    {
        $service ??= $this->service;

        if ($service?->linksDesignsToProducts() && $slug = $this->resolvedProductSlug()) {
            return route('shop.show', $slug);
        }

        if ($service && in_array($service->slug, Service::noDesignPageSlugs(), true)) {
            return null;
        }

        if ($service) {
            return route('services.design', [$service->slug, $this->slug]);
        }

        return null;
    }

    public function galleryCtaLabel(?Service $service = null): string
    {
        return 'Order Now';
    }
}
