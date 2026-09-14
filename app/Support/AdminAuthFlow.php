<?php

namespace App\Support;

use App\Models\User;
use App\Services\StaffIdentityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminAuthFlow
{
    public const SESSION_INTENDED = 'admin.url.intended';

    private const FAIL_MESSAGE = 'Invalid email or password.';

    public function __construct(
        private readonly AdminMfa $mfa,
        private readonly StaffIdentityService $staffIdentities,
    ) {}

    /**
     * After password or passkey verification: enforce admin rules and MFA before panel access.
     */
    public function completeAdminLogin(Request $request, User $user, string $via, ?string $emailHint = null): RedirectResponse
    {
        if (! $user->isAdmin() || ! $user->is_active) {
            Log::warning('admin.login_failed', [
                'email' => $emailHint ?? $user->email,
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'reason' => 'not_admin_or_inactive',
                'via' => $via,
            ]);

            auth()->logout();
            AdminAccess::revoke($request);
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['email' => self::FAIL_MESSAGE]);
        }

        if ($via === 'passkey') {
            // Passkey verification authenticates the user on the pre-login
            // session id. Rotate it before any admin state is granted so a
            // session fixated before sign-in can never become an admin session.
            $request->session()->regenerate(true);
        }

        AdminAccess::revoke($request);
        $request->session()->forget([AdminMfa::SESSION_SETUP_SECRET, AdminMfa::SESSION_LAST_TOTP]);

        $email = strtolower(trim((string) ($emailHint ?? $user->email)));

        if ($this->mfa->hasMfaEnabled($user)) {
            if ($via === 'passkey') {
                $this->staffIdentities->recordLogin($user, $request->ip());
                AdminAccess::grant($request, $user);

                Log::info('admin.login_succeeded', [
                    'user_id' => $user->id,
                    'email' => $email,
                    'ip' => $request->ip(),
                    'mfa' => 'passkey',
                    'via' => $via,
                ]);

                return self::intendedAdminRedirect($request);
            }

            $request->session()->put(AdminMfa::SESSION_PENDING, $user->id);

            Log::info('admin.login_ok_mfa_required', [
                'user_id' => $user->id,
                'email' => $email,
                'ip' => $request->ip(),
                'via' => $via,
            ]);

            return redirect()->route('admin.mfa.challenge');
        }

        if ($this->mfa->mustEnroll($user)) {
            $request->session()->put(AdminMfa::SESSION_PENDING, $user->id);

            Log::info('admin.login_ok_mfa_enroll_required', [
                'user_id' => $user->id,
                'email' => $email,
                'ip' => $request->ip(),
                'via' => $via,
            ]);

            return redirect()->route('admin.mfa.enroll')
                ->with('info', 'Two-factor authentication is required for admin access.');
        }

        $this->staffIdentities->recordLogin($user, $request->ip());
        AdminAccess::grant($request, $user);

        Log::info('admin.login_succeeded', [
            'user_id' => $user->id,
            'email' => $email,
            'ip' => $request->ip(),
            'mfa' => 'grace',
            'via' => $via,
        ]);

        return self::intendedAdminRedirect($request)
            ->with('info', 'Please enroll two-factor authentication before '
                .optional($user->two_factor_grace_ends_at)->timezone(config('app.timezone'))->format('d M Y H:i').'.');
    }

    /**
     * Remember a protected admin GET so login can restore it later.
     *
     * Storefront/customer intended URLs stay in url.intended and are never
     * reused as the admin landing page.
     */
    public static function rememberIntended(Request $request): void
    {
        if (! $request->isMethod('GET')) {
            return;
        }

        $candidate = $request->getRequestUri();

        if (self::isSafeAdminDestination($candidate)) {
            $request->session()->put(self::SESSION_INTENDED, $candidate);
        }
    }

    /**
     * Restore a safe same-origin /admin destination, otherwise the dashboard.
     *
     * Admin auth uses admin.url.intended. The shared url.intended key is only
     * consumed when it itself is a safe admin path, so leftover /cart or
     * /checkout values cannot hijack the first admin landing.
     */
    public static function intendedAdminRedirect(Request $request): RedirectResponse
    {
        $adminIntended = $request->session()->pull(self::SESSION_INTENDED);
        $sharedIntended = $request->session()->get('url.intended');

        foreach ([$adminIntended, $sharedIntended] as $index => $intended) {
            if (! self::isSafeAdminDestination($intended)) {
                continue;
            }

            if ($index === 1) {
                $request->session()->forget('url.intended');
            }

            return redirect()->to($intended);
        }

        return redirect()->route('admin.dashboard');
    }

    /**
     * Only same-origin paths under /admin, excluding auth/MFA/passkey loops.
     */
    public static function isSafeAdminDestination(mixed $url): bool
    {
        if (! is_string($url) || $url === '' || str_contains($url, "\0") || str_contains($url, '\\')) {
            return false;
        }

        $url = trim($url);

        if (! SafeInternalUrl::isSafe($url)) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return false;
        }

        $path = rawurldecode($parts['path'] ?? '');
        if ($path === '' || str_contains($path, '..')) {
            return false;
        }

        $adminPrefix = self::adminPathPrefix();
        $normalized = rtrim($path, '/') ?: '/';

        if ($normalized !== $adminPrefix && ! str_starts_with($path, $adminPrefix.'/')) {
            return false;
        }

        foreach (self::blockedAdminPathPrefixes() as $blocked) {
            if ($normalized === $blocked || str_starts_with($path, $blocked.'/')) {
                return false;
            }
        }

        return true;
    }

    private static function adminPathPrefix(): string
    {
        $path = parse_url(route('admin.dashboard', [], false), PHP_URL_PATH);

        return rtrim((string) $path, '/') ?: '/admin';
    }

    /** @return list<string> */
    private static function blockedAdminPathPrefixes(): array
    {
        $paths = [
            parse_url(route('admin.login', [], false), PHP_URL_PATH),
            parse_url(route('admin.logout', [], false), PHP_URL_PATH),
            parse_url(route('admin.mfa.challenge', [], false), PHP_URL_PATH),
            parse_url(route('admin.mfa.enroll', [], false), PHP_URL_PATH),
            parse_url(route('admin.passkeys.login', [], false), PHP_URL_PATH),
            parse_url(route('admin.passkeys.login.options', [], false), PHP_URL_PATH),
        ];

        $paths[] = self::adminPathPrefix().'/staff-invitations';

        return array_values(array_unique(array_filter(array_map(
            static fn ($path) => rtrim((string) $path, '/') ?: null,
            $paths,
        ))));
    }
}
