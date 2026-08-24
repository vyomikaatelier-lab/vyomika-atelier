<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CartService;
use App\Services\PhoneNumberService;
use App\Support\AdminAccess;
use App\Support\SafeInternalUrl;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use InvalidArgumentException;

class AccountAuthController extends Controller
{
    public const INVALID_CREDENTIALS = 'These credentials do not match our records.';

    public const RESET_LINK_STATUS = 'If an account exists for that email, we have sent password reset instructions.';

    public function __construct(
        private PhoneNumberService $phones,
        private CartService $cart,
    ) {}

    public function showLogin()
    {
        $this->forgetRetiredOtpState(request());

        return $this->authView('login');
    }

    public function showRegister()
    {
        $this->forgetRetiredOtpState(request());

        return $this->authView('register');
    }

    public function loginWithEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|string',
            'remember' => 'sometimes|boolean',
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->where('is_admin', false)
            ->first();

        if (! $user || ! $user->is_active || ! Hash::check($validated['password'], $user->password)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => self::INVALID_CREDENTIALS]);
        }

        $this->loginCustomerSession($request, $user, $request->boolean('remember'));

        return $this->redirectAfterAuth($request);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
            'country_code' => 'nullable|string|max:6',
            'mobile' => 'nullable|string|max:20',
        ]);

        $phone = null;
        if (filled($validated['mobile'] ?? null)) {
            try {
                $phone = $this->phones->normalize($validated['country_code'] ?: '+91', $validated['mobile']);
            } catch (InvalidArgumentException $e) {
                return back()->withInput($request->except('password', 'password_confirmation'))
                    ->withErrors(['mobile' => $e->getMessage()]);
            }

            $taken = User::query()
                ->where('mobile', $phone['national'])
                ->where('mobile_country_code', $phone['country_code'])
                ->exists();

            if ($taken) {
                return back()->withInput($request->except('password', 'password_confirmation'))
                    ->withErrors(['mobile' => 'This mobile number is already registered. Try signing in.']);
            }
        }

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'account_type' => 'customer',
            'is_admin' => false,
            'is_active' => true,
        ];

        if ($phone) {
            $attributes['mobile_country_code'] = $phone['country_code'];
            $attributes['mobile'] = $phone['national'];
        }

        $user = User::create($attributes);
        $user->forceFill([
            'is_admin' => false,
            'is_active' => true,
        ])->save();

        $this->loginCustomerSession($request, $user);

        return $this->redirectAfterAuth($request)
            ->with('success', 'Your account has been created.');
    }

    public function showForgot()
    {
        $this->forgetRetiredOtpState(request());

        return view('account.forgot');
    }

    public function sendResetLink(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->where('is_admin', false)
            ->where('is_active', true)
            ->first();

        if ($user) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', self::RESET_LINK_STATUS);
    }

    public function showReset(Request $request, string $token)
    {
        return view('account.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|max:255',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            [
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $validated['token'],
            ],
            function (User $user, string $password) use ($request) {
                if ($user->is_admin || ! $user->is_active) {
                    return;
                }

                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
                $this->loginCustomerSession($request, $user);
            }
        );

        if ($status === Password::PASSWORD_RESET && Auth::check()) {
            return $this->redirectAfterAuth($request)
                ->with('success', 'Your password has been updated.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'This password reset link is invalid or has expired.']);
    }

    public function showVerifyOtp()
    {
        $this->forgetRetiredOtpState(request());

        return redirect()->route('account.login');
    }

    public function rejectRetiredOtp(Request $request)
    {
        $this->forgetRetiredOtpState($request);

        return redirect()->route('account.login')
            ->with('info', 'Please sign in with your email and password.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('account.login')
            ->with('success', 'You have been signed out.');
    }

    private function loginCustomerSession(Request $request, User $user, bool $remember = false): void
    {
        AdminAccess::revoke($request);
        Auth::login($user, $remember);
        $request->session()->regenerate();
    }

    private function redirectAfterAuth(Request $request)
    {
        $default = $this->cart->hasBuyNow()
            ? route('checkout.index')
            : route('account');

        $intended = $request->session()->pull('url.intended');
        if (is_string($intended) && SafeInternalUrl::isSafe($intended)) {
            return redirect($intended);
        }

        return redirect($default);
    }

    private function authView(string $tab)
    {
        $intended = session('url.intended');

        return view('account.auth', [
            'tab' => $tab,
            'countryCodes' => config('account.country_codes', []),
            'socialProviders' => $this->socialProviders(),
            'purchaseIntent' => $this->cart->hasBuyNow()
                || (is_string($intended) && str_contains($intended, '/checkout')),
        ]);
    }

    private function socialProviders(): array
    {
        return [
            'google' => filled(config('services.google.client_id')) && filled(config('services.google.client_secret')),
            'apple' => filled(config('services.apple.client_id'))
                && filled(config('services.apple.client_secret'))
                && filled(config('services.apple.key_id'))
                && filled(config('services.apple.team_id')),
        ];
    }

    private function forgetRetiredOtpState(Request $request): void
    {
        $request->session()->forget([
            'account_pending_verification_id',
            'account_pending_mobile_display',
            'account_register_password',
            'account_register_password_confirmed',
        ]);
    }
}
