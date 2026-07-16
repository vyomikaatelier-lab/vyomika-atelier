<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'title', 'slug', 'summary', 'content', 'image', 'gallery',
        'location', 'year', 'category', 'client', 'design_details', 'materials', 'finishes',
        'scope', 'challenges',
        'testimonial_quote', 'testimonial_author', 'testimonial_role',
        'completed_at', 'is_featured', 'is_active', 'display_order',
        'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'materials' => 'array',
            'finishes' => 'array',
            'completed_at' => 'date',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return array<string, string> */
    public static function categoryLabels(): array
    {
        return [
            'residential' => 'Residential',
            'commercial' => 'Commercial',
            'hospitality' => 'Hospitality',
        ];
    }

    public function categoryLabel(): ?string
    {
        return $this->category
            ? (self::categoryLabels()[$this->category] ?? ucfirst($this->category))
            : null;
    }

    public function seoTitle(): string
    {
        if ($this->meta_title) {
            return $this->meta_title;
        }

        $location = $this->location ? ' in ' . explode(',', $this->location)[0] : '';

        return $this->title . ' — Metalwork' . $location . ' | Vyomika Atelier LLP';
    }

    public function seoDescription(): string
    {
        return $this->meta_description
            ?? ($this->summary . ' — Custom PVD and architectural metalwork by Vyomika Atelier LLP.');
    }

    public function hasTestimonial(): bool
    {
        return filled($this->testimonial_quote);
    }
}
