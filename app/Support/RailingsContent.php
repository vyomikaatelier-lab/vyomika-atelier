<?php

namespace App\Support;

class RailingsContent
{
    public static function all(): array
    {
        return LandingPageContent::page('railings');
    }
}
