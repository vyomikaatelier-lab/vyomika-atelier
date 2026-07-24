<?php

namespace App\Support;

use Illuminate\Http\Request;

class AdminAccess
{
    public const SESSION_KEY = 'admin.access_verified';

    public static function grant(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, true);
    }

    public static function revoke(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public static function verified(Request $request): bool
    {
        return (bool) $request->session()->get(self::SESSION_KEY, false);
    }
}
