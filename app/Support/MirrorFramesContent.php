<?php

namespace App\Support;

class MirrorFramesContent
{
    public static function all(): array
    {
        return config('mirror-frames', []);
    }

    public static function design(string $slug): ?array
    {
        foreach (self::all()['designs'] ?? [] as $design) {
            if (($design['slug'] ?? '') === $slug) {
                return $design;
            }
        }

        return null;
    }
}
