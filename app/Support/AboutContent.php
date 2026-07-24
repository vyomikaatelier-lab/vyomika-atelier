<?php

namespace App\Support;

class AboutContent
{
    public static function all(): array
    {
        $about = config('about', []);
        $about['hero'] = PageHeroContent::heroWithResolvedImages('about');
        $about['exhibitions']['events'] = CmsSettings::exhibitions();

        return $about;
    }

    public static function metaTitle(): string
    {
        return config('about.meta_title', 'About Vyomika Atelier LLP');
    }

    public static function metaDescription(): string
    {
        return config('about.meta_description', '');
    }
}
