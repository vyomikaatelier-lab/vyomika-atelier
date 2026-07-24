<?php

namespace App\Support;

class CortenContent
{
    public static function all(): array
    {
        return LandingPageContent::page('corten-steel');
    }

    public static function metaTitle(): string
    {
        return (string) (self::all()['meta_title'] ?? 'Corten Steel — Vyomika Atelier LLP');
    }

    public static function metaDescription(): string
    {
        return (string) (self::all()['meta_description'] ?? '');
    }
}
