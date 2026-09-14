<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class AdminAccess
{
    public const SESSION_KEY = 'admin.access_verified';

    public const SESSION_VERSION_KEY = 'admin.access_version';

    public const SESSION_USER_KEY = 'admin.access_user_id';

    public static function grant(Request $request, ?User $user = null): void
    {
        $user ??= $request->user() ?? auth()->user();

        $request->session()->put([
            self::SESSION_KEY => true,
            self::SESSION_VERSION_KEY => (int) ($user?->admin_session_version ?? 1),
            self::SESSION_USER_KEY => $user?->getKey(),
        ]);
    }

    public static function revoke(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_KEY,
            self::SESSION_VERSION_KEY,
            self::SESSION_USER_KEY,
        ]);
    }

    public static function verified(Request $request): bool
    {
        if (! (bool) $request->session()->get(self::SESSION_KEY, false)) {
            return false;
        }

        $boundUserId = $request->session()->get(self::SESSION_USER_KEY);
        $user = $request->user() ?? auth()->user();

        if (! $user && is_numeric($boundUserId)) {
            $user = User::query()->find((int) $boundUserId);
        }

        if (! $user?->isAdmin() || ! $user->is_active) {
            return false;
        }

        $sessionVersion = $request->session()->get(self::SESSION_VERSION_KEY);

        // Preserve pre-RBAC sessions for legacy admins only. As soon as an
        // explicit role is assigned, every admin session becomes versioned.
        if ($user->admin_role === null && $sessionVersion === null) {
            return true;
        }

        return is_numeric($boundUserId)
            && (int) $boundUserId === (int) $user->getKey()
            && is_numeric($sessionVersion)
            && (int) $sessionVersion === (int) ($user->admin_session_version ?? 1);
    }
}
