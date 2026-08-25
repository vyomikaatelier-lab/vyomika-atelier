<?php

namespace App\Support;

class SafeInternalUrl
{
    public static function isSafe(?string $url): bool
    {
        if (! is_string($url) || $url === '' || str_contains($url, "\0")) {
            return false;
        }

        $url = trim($url);

        if (str_starts_with($url, '//') || str_starts_with($url, '\\\\')) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return false;
        }

        if (! empty($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        if (! empty($parts['host'])) {
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            $requestHost = request()->getHost();
            $allowed = array_values(array_filter([$appHost, $requestHost]));

            if ($allowed === [] || ! in_array($parts['host'], $allowed, true)) {
                return false;
            }
        }

        $path = $parts['path'] ?? '/';

        return ! str_contains($path, '..');
    }
}
