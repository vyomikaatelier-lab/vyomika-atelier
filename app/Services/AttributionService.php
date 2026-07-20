<?php

namespace App\Services;

use Illuminate\Http\Request;

class AttributionService
{
  private const SESSION_KEY = 'va_attribution';

  public function captureFromRequest(Request $request): void
  {
    $existing = $request->session()->get(self::SESSION_KEY, []);
    $utm = array_filter([
      'utm_source' => $request->query('utm_source'),
      'utm_medium' => $request->query('utm_medium'),
      'utm_campaign' => $request->query('utm_campaign'),
      'utm_term' => $request->query('utm_term'),
      'utm_content' => $request->query('utm_content'),
    ]);

    $touch = $this->resolveTouchSource($utm, $request);
    $device = $this->detectDevice($request->userAgent() ?? '');

    $payload = [
      'utm_source' => $utm['utm_source'] ?? ($existing['utm_source'] ?? null),
      'utm_medium' => $utm['utm_medium'] ?? ($existing['utm_medium'] ?? null),
      'utm_campaign' => $utm['utm_campaign'] ?? ($existing['utm_campaign'] ?? null),
      'utm_term' => $utm['utm_term'] ?? ($existing['utm_term'] ?? null),
      'utm_content' => $utm['utm_content'] ?? ($existing['utm_content'] ?? null),
      'referrer' => $request->headers->get('referer') ?: ($existing['referrer'] ?? null),
      'landing_page' => $existing['landing_page'] ?? $request->fullUrl(),
      'first_touch_source' => $existing['first_touch_source'] ?? $touch,
      'last_touch_source' => $touch ?: ($existing['last_touch_source'] ?? null),
      'device_type' => $device,
    ];

    if (! empty($utm)) {
      $payload = array_merge($payload, $utm);
      $payload['last_touch_source'] = $touch;
    }

    $request->session()->put(self::SESSION_KEY, $payload);
  }

  /**
   * @return array<string, string|null>
   */
  public function forLeadCreation(Request $request): array
  {
    $data = $request->session()->get(self::SESSION_KEY, []);

    return [
      'utm_source' => $data['utm_source'] ?? null,
      'utm_medium' => $data['utm_medium'] ?? null,
      'utm_campaign' => $data['utm_campaign'] ?? null,
      'utm_term' => $data['utm_term'] ?? null,
      'utm_content' => $data['utm_content'] ?? null,
      'referrer' => $data['referrer'] ?? null,
      'landing_page' => $data['landing_page'] ?? null,
      'first_touch_source' => $data['first_touch_source'] ?? null,
      'last_touch_source' => $data['last_touch_source'] ?? null,
      'device_type' => $data['device_type'] ?? $this->detectDevice($request->userAgent() ?? ''),
    ];
  }

  /**
   * @param  array<string, string>  $utm
   */
  private function resolveTouchSource(array $utm, Request $request): ?string
  {
    if (! empty($utm['utm_source'])) {
      $medium = $utm['utm_medium'] ?? 'unknown';

      return $utm['utm_source'] . ' / ' . $medium;
    }

    $referer = $request->headers->get('referer');
    if ($referer) {
      $host = parse_url($referer, PHP_URL_HOST);
      if ($host && ! str_contains($host, parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost')) {
        return 'referral: ' . $host;
      }
    }

    return 'direct';
  }

  private function detectDevice(string $userAgent): string
  {
    $ua = strtolower($userAgent);
    if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
      return 'mobile';
    }
    if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
      return 'tablet';
    }

    return 'desktop';
  }
}
