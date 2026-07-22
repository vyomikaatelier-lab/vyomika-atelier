<?php

namespace App\Services;

use App\Support\DisposableEmailChecker;
use App\Support\IpFingerprint;
use App\Support\LeadProtectionStatus;
use App\Support\SpamContentAnalyzer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;

class FormProtectionService
{
    public function __construct(
        private TurnstileService $turnstile,
    ) {}

    public function formLoadedToken(string $formKey): string
    {
        return Crypt::encryptString(json_encode([
            'form' => $formKey,
            'loaded_at' => now()->timestamp,
        ]));
    }

    /**
     * @return array{reject: bool, rate_limited: bool, message: string|null, duration_ms: int|null, reasons: list<string>}
     */
    public function validateSubmission(Request $request, string $formKey, bool $requireIntent = true): array
    {
        $reasons = [];

        if ($this->honeypotTripped($request)) {
            return $this->hardReject(['honeypot_filled']);
        }

        $duration = $this->submissionDurationMs($request, $formKey);
        if ($duration !== null && $duration < (int) config('form_protection.min_submission_seconds', 3) * 1000) {
            return $this->hardReject(['submission_too_fast']);
        }

        if (! $this->validateHumanChallenge($request, $formKey)) {
            return $this->hardReject(['human_verification_failed']);
        }

        if ($requireIntent && ! $this->validEnquiryIntent($request->input('enquiry_intent'))) {
            return $this->hardReject(['invalid_enquiry_intent']);
        }

        if ($this->isRateLimited($request, $formKey)) {
            return [
                'reject' => true,
                'rate_limited' => true,
                'message' => config('form_protection.messages.rate_limited'),
                'duration_ms' => $duration,
                'reasons' => ['rate_limited'],
            ];
        }

        return [
            'reject' => false,
            'rate_limited' => false,
            'message' => null,
            'duration_ms' => $duration,
            'reasons' => $reasons,
        ];
    }

    public function hitRateLimiters(Request $request, string $formKey): void
    {
        $group = config("form_protection.form_groups.{$formKey}", 'general_enquiry');
        $limits = config("form_protection.rate_limits.{$group}", config('form_protection.rate_limits.general_enquiry'));
        $decay = ((int) ($limits['decay_minutes'] ?? 15)) * 60;

        $keys = $this->rateLimitKeys($request, $formKey);
        foreach ($keys as $key) {
            RateLimiter::hit($key, $decay);
        }
    }

    public function isRateLimited(Request $request, string $formKey): bool
    {
        $group = config("form_protection.form_groups.{$formKey}", 'general_enquiry');
        $limits = config("form_protection.rate_limits.{$group}", config('form_protection.rate_limits.general_enquiry'));
        $max = (int) ($limits['max'] ?? 3);

        foreach ($this->rateLimitKeys($request, $formKey) as $key) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function rateLimitKeys(Request $request, string $formKey): array
    {
        $group = config("form_protection.form_groups.{$formKey}", 'general_enquiry');
        $ip = $request->ip() ?? 'unknown';
        $sessionId = $request->session()->getId() ?: 'no-session';

        $keys = [
            "form-protection:{$group}:ip:{$ip}",
            "form-protection:{$group}:session:{$sessionId}",
        ];

        if ($email = strtolower(trim((string) $request->input('email')))) {
            $keys[] = "form-protection:{$group}:email:" . hash('sha256', $email);
        }

        $phone = $this->normalizePhone($request);
        if ($phone) {
            $keys[] = "form-protection:{$group}:phone:" . hash('sha256', $phone);
        }

        if (in_array($formKey, ['account_register', 'account_login_otp', 'account_forgot_otp'], true)) {
            $country = $request->input('country_code', '+91');
            $mobile = preg_replace('/\D/', '', (string) $request->input('mobile'));
            if ($mobile) {
                $keys[] = "form-protection:otp_send:phone:" . hash('sha256', $country . $mobile);
            }
        }

        return array_values(array_unique($keys));
    }

    public function normalizePhone(Request $request): ?string
    {
        $phone = preg_replace('/\D/', '', (string) ($request->input('phone') ?: $request->input('mobile') ?: $request->input('whatsapp')));

        return $phone !== '' ? $phone : null;
    }

    public function messageFingerprint(string $message): string
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $message) ?? ''));

        return hash('sha256', $normalized);
    }

    private function honeypotTripped(Request $request): bool
    {
        $field = config('form_protection.honeypot_field', 'va_contact_url');

        return filled($request->input($field));
    }

    private function submissionDurationMs(Request $request, string $formKey): ?int
    {
        $token = $request->input('form_loaded_at');
        if (! filled($token)) {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($payload) || ($payload['form'] ?? null) !== $formKey) {
            return null;
        }

        $loadedAt = (int) ($payload['loaded_at'] ?? 0);
        $maxAge = (int) config('form_protection.max_submission_age_seconds', 7200);
        $ageSeconds = now()->timestamp - $loadedAt;

        if ($loadedAt <= 0 || $ageSeconds > $maxAge) {
            return null;
        }

        return max(0, $ageSeconds * 1000);
    }

    private function validateHumanChallenge(Request $request, string $formKey): bool
    {
        if (config('form_protection.turnstile.require_manual_confirmation', true)
            && ! $request->boolean('human_confirmation')) {
            return false;
        }

        $token = $request->input('cf-turnstile-response');
        $ip = $request->ip();

        if ($this->turnstile->verify($token, $ip)) {
            return true;
        }

        $usingFallback = (bool) $request->boolean('turnstile_unavailable');
        if (! $usingFallback) {
            return false;
        }

        if (! $this->turnstile->validateFallbackToken($request->input('turnstile_fallback_token'), $formKey)) {
            return false;
        }

        return true;
    }

    private function validEnquiryIntent(mixed $intent): bool
    {
        return is_string($intent) && array_key_exists($intent, config('form_protection.enquiry_intents', []));
    }

    /**
     * @param  list<string>  $reasons
     * @return array{reject: bool, rate_limited: bool, message: string|null, duration_ms: int|null, reasons: list<string>}
     */
    private function hardReject(array $reasons): array
    {
        return [
            'reject' => true,
            'rate_limited' => false,
            'message' => config('form_protection.messages.generic_reject'),
            'duration_ms' => null,
            'reasons' => $reasons,
        ];
    }
}
