<?php

namespace App\Support;

class AnnouncementGuard
{
    /** Debug / sync markers that must never appear on the public storefront. */
    private const BLOCKED_PATTERNS = [
        '/SYNC-TRACE/i',
        '/sync[_-]?trace/i',
        '/DEBUG[_-]?MARKER/i',
    ];

    /** @param  array<string, mixed>  $announcement */
    public static function sanitize(array $announcement): array
    {
        $text = trim((string) ($announcement['text'] ?? ''));

        if ($text === '' || self::isBlocked($text)) {
            $announcement['text'] = null;
            $announcement['link_label'] = null;
            $announcement['link_href'] = null;

            return $announcement;
        }

        $announcement['text'] = $text;

        return $announcement;
    }

    public static function isBlocked(?string $text): bool
    {
        if ($text === null || $text === '') {
            return false;
        }

        foreach (self::BLOCKED_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }
}
