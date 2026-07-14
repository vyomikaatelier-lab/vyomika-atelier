<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'title', 'slug', 'summary', 'content', 'image', 'gallery',
        'location', 'completed_at', 'is_featured', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'completed_at' => 'date',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
