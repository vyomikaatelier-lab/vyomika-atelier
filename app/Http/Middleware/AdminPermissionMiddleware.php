<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user?->hasAdminPermission($permission)) {
            Log::warning('admin.permission_denied', [
                'user_id' => $user?->id,
                'staff_id' => $user?->staff_id,
                'permission' => $permission,
                'route' => $request->route()?->getName(),
                'ip' => $request->ip(),
            ]);

            abort(403, 'You do not have permission to perform this admin action.');
        }

        return $next($request);
    }
}
