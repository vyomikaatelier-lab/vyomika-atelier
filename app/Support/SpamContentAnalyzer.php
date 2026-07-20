<?php

namespace App\Support;

final class SpamContentAnalyzer
{
    /**
     * @return array{url_count: int, spam_phrases: list<string>}
     */
    public static function analyze(string $text): array
    {
        $normalized = strtolower($text);
        $urlCount = preg_match_all('/https?:\/\/|www\./i', $text) ?: 0;

        $matched = [];
        foreach (config('form_protection.spam_phrases', []) as $phrase) {
            if (str_contains($normalized, strtolower($phrase))) {
                $matched[] = $phrase;
            }
        }

        return [
            'url_count' => $urlCount,
            'spam_phrases' => $matched,
        ];
    }

    public static function isSuspicious(string $text): bool
    {
        $analysis = self::analyze($text);

        return $analysis['url_count'] > (int) config('form_protection.max_urls_in_message', 3)
            || $analysis['spam_phrases'] !== [];
    }
}
