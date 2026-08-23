<?php

namespace App\Support;

class BlogHtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'a', 'strong', 'b', 'em', 'i',
        'blockquote', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'figure', 'figcaption', 'br', 'hr', 'span', 'div',
    ];

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $allowed = '<'.implode('><', self::ALLOWED_TAGS).'>';
        $clean = strip_tags($html, $allowed);

        return trim($clean) !== '' ? $clean : null;
    }
}
