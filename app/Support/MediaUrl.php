<?php

namespace App\Support;

class MediaUrl
{
    public static function resolve(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        return asset('storage/'.$path);
    }

    /** @param array<int, string>|null $items */
    public static function resolveMany(?array $items): array
    {
        $urls = [];

        foreach ($items ?? [] as $item) {
            if (! is_string($item) || $item === '') {
                continue;
            }

            $resolved = self::resolve($item);
            if ($resolved) {
                $urls[] = $resolved;
            }
        }

        return array_values(array_unique($urls));
    }
}
