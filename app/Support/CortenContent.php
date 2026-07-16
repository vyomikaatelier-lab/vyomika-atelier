<?php

namespace App\Support;

class CortenContent
{
    public static function all(): array
    {
        return config('corten', []);
    }

    public static function metaTitle(): string
    {
        return config('corten.meta_title', 'Corten Steel — Vyomika Atelier LLP');
    }

    public static function metaDescription(): string
    {
        return config('corten.meta_description', '');
    }
}
