<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalPage extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'meta_title',
        'meta_description',
        'sections',
        'content_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'content_updated_at' => 'date',
        ];
    }
}
