<?php

namespace App\Support;

class RailingsContent
{
    public static function all(): array
    {
        return config('railings', []);
    }
}
