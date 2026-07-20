<?php

namespace App\Http\Middleware;

use App\Services\AttributionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureAttribution
{
  public function __construct(
    private AttributionService $attribution,
  ) {}

  public function handle(Request $request, Closure $next): Response
  {
    if ($request->isMethod('GET') && ! $request->expectsJson()) {
      $this->attribution->captureFromRequest($request);
    }

    return $next($request);
  }
}
