<?php

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'project_name',
        'work_type',
        'city',
        'client',
        'size',
        'price',
        'description',
        'image_path',
        'image_alt',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function imageUrl(): ?string
    {
        return MediaUrl::resolve($this->image_path);
    }

    public function displayAlt(): string
    {
        return filled($this->image_alt)
            ? $this->image_alt
            : ($this->project_name.' — Vyomika Atelier');
    }
}
