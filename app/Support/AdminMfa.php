<?php

namespace App\Support;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class AdminMfa
{
    public const SESSION_PENDING = 'admin.mfa_pending_user_id';

    public const SESSION_SETUP_SECRET = 'admin.mfa_setup_secret';

    public const SESSION_LAST_TOTP = 'admin.mfa_last_totp_at';

    public function __construct(
        private readonly Google2FA $google2fa = new Google2FA,
    ) {}

    public function graceDays(): int
    {
        return max(0, (int) config('admin_mfa.grace_days', 7));
    }

    public function issuer(): string
    {
        return (string) config('admin_mfa.issuer', config('app.name', 'Vyomika Atelier'));
    }

    public function hasMfaEnabled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null
            && filled($user->two_factor_secret);
    }

    public function graceExpired(User $user): bool
    {
        if ($this->hasMfaEnabled($user)) {
            return false;
        }

        $ends = $user->two_factor_grace_ends_at;

        if ($ends === null) {
            // New admins without an explicit grace window must enroll immediately.
            return true;
        }

        return $ends->isPast();
    }

    public function mustEnroll(User $user): bool
    {
        return $user->isAdmin() && ! $this->hasMfaEnabled($user) && $this->graceExpired($user);
    }

    public function mayDeferEnrollment(User $user): bool
    {
        return $user->isAdmin() && ! $this->hasMfaEnabled($user) && ! $this->graceExpired($user);
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function qrUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            $this->issuer(),
            $user->email,
            $secret
        );
    }

    public function qrSvgDataUri(string $otpAuthUrl): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(220),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($otpAuthUrl);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function encryptSecret(string $secret): string
    {
        return Crypt::encryptString($secret);
    }

    public function decryptSecret(?string $payload): ?string
    {
        if (! filled($payload)) {
            return null;
        }

        try {
            return Crypt::decryptString($payload);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<string> Plaintext recovery codes (show once). */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(4).'-'.Str::random(4));
        }

        return $codes;
    }

    /** @param  list<string>  $plainCodes */
    public function hashRecoveryCodes(array $plainCodes): string
    {
        $hashed = array_map(
            fn (string $code) => password_hash($this->normalizeRecoveryCode($code), PASSWORD_DEFAULT),
            $plainCodes
        );

        return Crypt::encryptString(json_encode(array_values($hashed), JSON_THROW_ON_ERROR));
    }

    /** @return list<string> */
    public function decryptRecoveryCodeHashes(?string $payload): array
    {
        if (! filled($payload)) {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString($payload), true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? array_values($decoded) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function verifyTotp(User $user, string $code, ?string $setupSecret = null): bool
    {
        $secret = $setupSecret ?? $this->decryptSecret($user->two_factor_secret);
        if (! filled($secret)) {
            return false;
        }

        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timestamp = $this->google2fa->getTimestamp();
        $window = (int) config('admin_mfa.window', 1);

        if (! $this->google2fa->verifyKey($secret, $code, $window, $timestamp)) {
            return false;
        }

        // Replay protection: reject the same OTP window reuse in this session.
        $last = session(self::SESSION_LAST_TOTP);
        if (is_numeric($last) && (int) $last === (int) $timestamp) {
            return false;
        }

        session([self::SESSION_LAST_TOTP => $timestamp]);

        return true;
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $hashes = $this->decryptRecoveryCodeHashes($user->two_factor_recovery_codes);
        if ($hashes === []) {
            return false;
        }

        $normalized = $this->normalizeRecoveryCode($code);
        $remaining = [];
        $matched = false;

        foreach ($hashes as $hash) {
            if (! $matched && password_verify($normalized, $hash)) {
                $matched = true;

                continue;
            }
            $remaining[] = $hash;
        }

        if (! $matched) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($remaining, JSON_THROW_ON_ERROR)),
        ])->save();

        return true;
    }

    public function enable(User $user, string $plainSecret, array $plainRecoveryCodes): void
    {
        $user->forceFill([
            'two_factor_secret' => $this->encryptSecret($plainSecret),
            'two_factor_recovery_codes' => $this->hashRecoveryCodes($plainRecoveryCodes),
            'two_factor_confirmed_at' => now(),
            'two_factor_grace_ends_at' => null,
        ])->save();
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_grace_ends_at' => now(), // require immediate re-enroll after ops reset
        ])->save();
    }

    public function assignImmediateEnrollment(User $user): void
    {
        if ($this->hasMfaEnabled($user)) {
            return;
        }

        $user->forceFill([
            'two_factor_grace_ends_at' => now(),
        ])->save();
    }

    public function assignGraceWindow(User $user): void
    {
        if ($this->hasMfaEnabled($user) || $user->two_factor_grace_ends_at !== null) {
            return;
        }

        $user->forceFill([
            'two_factor_grace_ends_at' => now()->addDays($this->graceDays()),
        ])->save();
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(preg_replace('/[\s\-]+/', '', $code) ?? '');
    }
}
