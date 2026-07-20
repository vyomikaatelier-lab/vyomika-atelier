<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileService
{
    public function isConfigured(): bool
    {
        return filled(config('form_protection.turnstile.site_key'))
            && filled(config('form_protection.turnstile.secret_key'));
    }

    public function siteKey(): ?string
    {
        return config('form_protection.turnstile.site_key');
    }

    public function verify(?string $token, ?string $ip = null, bool $usingFallback = false): bool
    {
        if (config('form_protection.turnstile.skip_verification')) {
            return true;
        }

        $bypass = config('form_protection.turnstile.testing_bypass_token');
        if ($bypass && hash_equals((string) $bypass, (string) $token)) {
            return true;
        }

        if ($usingFallback) {
            return false;
        }

        if (! $this->isConfigured() || ! filled($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => config('form_protection.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]);

            if (! $response->successful()) {
                Log::warning('Turnstile verification HTTP error', ['status' => $response->status()]);

                return false;
            }

            return (bool) ($response->json('success') ?? false);
        } catch (\Throwable $e) {
            Log::warning('Turnstile verification failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function fallbackToken(string $formKey): string
    {
        return encrypt([
            'form' => $formKey,
            'issued_at' => now()->timestamp,
        ]);
    }

    public function validateFallbackToken(?string $token, string $formKey): bool
    {
        if (! filled($token)) {
            return false;
        }

        try {
            $payload = decrypt($token);

            return is_array($payload)
                && ($payload['form'] ?? null) === $formKey
                && now()->timestamp - (int) ($payload['issued_at'] ?? 0) <= (int) config('form_protection.max_submission_age_seconds', 7200);
        } catch (\Throwable) {
            return false;
        }
    }
}
