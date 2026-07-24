<?php

namespace App\Http\Middleware;

use App\Models\UrlRedirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class ApplyUrlRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Schema::hasTable('url_redirects')) {
            return $next($request);
        }

        $path = UrlRedirect::normalizePath('/'.$request->path());

        $redirect = UrlRedirect::query()
            ->where('is_active', true)
            ->where('from_path', $path)
            ->first();

        if (! $redirect) {
            return $next($request);
        }

        $target = $redirect->to_url;
        if (! str_starts_with($target, 'http://') && ! str_starts_with($target, 'https://')) {
            $target = url($target);
        }

        $status = in_array((int) $redirect->status_code, [301, 302, 307, 308], true)
            ? (int) $redirect->status_code
            : 301;

        return redirect()->to($target, $status);
    }
}
