<?php

namespace App\Services;

use App\Contracts\WhatsAppProvider;
use App\Exceptions\WhatsAppDeliveryException;
use App\Exceptions\WhatsAppNotConfiguredException;
use App\Models\WhatsappOtpVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class WhatsappOtpService
{
    public function __construct(
        private WhatsAppProvider $whatsApp,
        private PhoneNumberService $phones,
    ) {}

    public function providerConfigured(): bool
    {
        return $this->whatsApp->isConfigured();
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function send(string $mobileE164, string $purpose, ?array $payload, string $ip): WhatsappOtpVerification
    {
        if (! $this->providerConfigured()) {
            throw new WhatsAppNotConfiguredException;
        }

        $this->enforceSendRateLimits($mobileE164, $ip);

        WhatsappOtpVerification::query()
            ->where('mobile_e164', $mobileE164)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->delete();

        $otp = $this->generateOtp();
        $expiresAt = now()->addMinutes((int) config('account.otp.expiry_minutes', 5));

        $record = WhatsappOtpVerification::create([
            'mobile_e164' => $mobileE164,
            'purpose' => $purpose,
            'otp_hash' => Hash::make($otp),
            'payload' => $payload,
            'attempts' => 0,
            'send_count' => 1,
            'ip_address' => $ip,
            'expires_at' => $expiresAt,
        ]);

        try {
            $this->whatsApp->sendOtp($mobileE164, $otp);
        } catch (WhatsAppDeliveryException $e) {
            $record->delete();
            throw $e;
        }

        RateLimiter::hit($this->sendKey($mobileE164), 3600);
        RateLimiter::hit($this->sendIpKey($ip), 3600);
        RateLimiter::hit($this->resendDelayKey($mobileE164), (int) config('account.otp.resend_delay_seconds', 30));

        Log::info('OTP verification initiated', [
            'mobile_e164' => $mobileE164,
            'purpose' => $purpose,
            'verification_id' => $record->id,
        ]);

        return $record;
    }

    public function resend(WhatsappOtpVerification $record, string $ip): WhatsappOtpVerification
    {
        if ($record->isVerified()) {
            throw new \RuntimeException('OTP already verified.');
        }

        return $this->send(
            $record->mobile_e164,
            $record->purpose,
            $record->payload,
            $ip,
        );
    }

    public function verify(WhatsappOtpVerification $record, string $otp): bool
    {
        if ($record->isVerified()) {
            return true;
        }

        if ($record->isExpired()) {
            return false;
        }

        if (! $record->hasAttemptsRemaining()) {
            return false;
        }

        $record->increment('attempts');

        if (! Hash::check($otp, $record->otp_hash)) {
            Log::info('OTP verification failed', [
                'verification_id' => $record->id,
                'mobile_e164' => $record->mobile_e164,
                'purpose' => $record->purpose,
                'attempt' => $record->attempts,
            ]);

            return false;
        }

        $record->update(['verified_at' => now()]);

        Log::info('OTP verification succeeded', [
            'verification_id' => $record->id,
            'mobile_e164' => $record->mobile_e164,
            'purpose' => $record->purpose,
        ]);

        return true;
    }

    public function secondsUntilResend(string $mobileE164): int
    {
        return RateLimiter::availableIn($this->resendDelayKey($mobileE164));
    }

    public function canResend(string $mobileE164): bool
    {
        return $this->secondsUntilResend($mobileE164) <= 0
            && RateLimiter::remaining($this->sendKey($mobileE164), (int) config('account.otp.max_sends_per_hour', 5)) > 0;
    }

    private function enforceSendRateLimits(string $mobileE164, string $ip): void
    {
        $maxPerHour = (int) config('account.otp.max_sends_per_hour', 5);

        if (RateLimiter::tooManyAttempts($this->sendKey($mobileE164), $maxPerHour)) {
            throw new \RuntimeException('Too many OTP requests for this number. Please try again later.');
        }

        if (RateLimiter::tooManyAttempts($this->sendIpKey($ip), $maxPerHour * 2)) {
            throw new \RuntimeException('Too many OTP requests from this connection. Please try again later.');
        }

        $delay = $this->secondsUntilResend($mobileE164);
        if ($delay > 0) {
            throw new \RuntimeException("Please wait {$delay} seconds before requesting another code.");
        }
    }

    private function generateOtp(): string
    {
        $length = (int) config('account.otp.length', 6);

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    private function sendKey(string $mobileE164): string
    {
        return 'otp-send:' . $mobileE164;
    }

    private function sendIpKey(string $ip): string
    {
        return 'otp-send-ip:' . $ip;
    }

    private function resendDelayKey(string $mobileE164): string
    {
        return 'otp-resend-delay:' . $mobileE164;
    }
}
