<?php

namespace App\Models;

use App\Support\MediaReferenceScanner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
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

    /**
     * Number of first-party records referencing this file's path.
     *
     * Fails closed: when references cannot be determined the file is treated
     * as referenced so it can never be deleted on an indeterminate result.
     */
    public function referenceCount(): int
    {
        try {
            return app(MediaReferenceScanner::class)->referenceCount((string) $this->path);
        } catch (\Throwable $e) {
            Log::warning('media.reference_scan_failed', [
                'media_file_id' => $this->id,
                'path' => $this->path,
                'error' => $e->getMessage(),
            ]);

            return 1;
        }
    }
}
