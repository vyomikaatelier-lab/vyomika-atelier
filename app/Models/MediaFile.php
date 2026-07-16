<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaFile extends Model
{
    protected $fillable = [
        'disk',
        'path',
        'filename',
        'mime',
        'size',
        'alt',
        'is_private',
    ];

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
        ];
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime ?? '', 'image/');
    }

    public function referenceCount(): int
    {
        $path = $this->path;
        $count = 0;

        $count += Product::query()->where('image', $path)->orWhere('gallery', 'like', '%' . $path . '%')->count();
        $count += Category::query()->where('image', $path)->count();
        $count += Project::query()->where('image', $path)->orWhere('gallery', 'like', '%' . $path . '%')->count();
        $count += BlogPost::query()->where('image', $path)->orWhere('gallery', 'like', '%' . $path . '%')->count();
        $count += Exhibition::query()->where('cover_image', $path)->orWhere('gallery', 'like', '%' . $path . '%')->count();

        return $count;
    }
}
