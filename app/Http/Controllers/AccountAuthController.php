<?php

namespace App\Http\Controllers;

use App\Exceptions\WhatsAppDeliveryException;
use App\Exceptions\WhatsAppNotConfiguredException;
use App\Models\User;
use App\Models\WhatsappOtpVerification;
use App\Services\FormProtectionService;
use App\Services\PhoneNumberService;
use App\Services\WhatsappOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class AccountAuthController extends Controller
{
    public function __construct(
        private WhatsappOtpService $otp,
        private PhoneNumberService $phones,
        private FormProtectionService $formProtection,
    ) {}

    public function showLogin()
    {
        return $this->authView('login');
    }

    public function sendLoginOtp(Request $request)
    {
        return $this->dispatchOtp($request, 'login');
    }

    public function loginWithEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->where('is_admin', false)
            ->whereNotNull('phone_verified_at')
            ->first();

        if (! $user || ! $user->is_active || ! Hash::check($validated['password'], $user->password)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Invalid email or password.'])
                ->with('login_method', 'email');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('account'));
    }

    public function loginWithMobilePassword(Request $request)
    {
        $validated = $request->validate([
            'country_code' => 'required|string|max:6',
            'mobile' => 'required|string|max:20',
            'password' => 'required|string',
        ]);

        try {
            $phone = $this->phones->normalize($validated['country_code'], $validated['mobile']);
        } catch (InvalidArgumentException $e) {
            return back()
                ->withInput()
                ->withErrors(['mobile' => $e->getMessage()])
                ->with('login_method', 'mobile')
                ->with('mobile_login_mode', 'password');
        }

        $user = User::query()
            ->where('mobile', $phone['national'])
            ->where('mobile_country_code', $phone['country_code'])
            ->where('is_admin', false)
            ->whereNotNull('phone_verified_at')
            ->first();

        if (! $user || ! $user->is_active || ! Hash::check($validated['password'], $user->password)) {
            return back()
                ->withInput()
                ->withErrors(['mobile' => 'Invalid mobile number or password.'])
                ->with('login_method', 'mobile')
                ->with('mobile_login_mode', 'password');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('account'));
    }

    public function showRegister(Request $request)
    {
        if ($request->boolean('change_number')) {
            $request->session()->forget([
                'account_pending_verification_id',
                'account_pending_mobile_display',
                'account_register_password',
            ]);

            return redirect()->route('account.register');
        }

        return $this->authView('register');
    }

    public function sendRegisterOtp(Request $request)
    {
        if ($response = $this->guardOtpForm($request, 'account_register')) {
            return $response;
        }

        if (! $this->otp->providerConfigured()) {
            return back()->withInput()->withErrors([
                'mobile' => 'WhatsApp verification is not available yet. Please contact the studio.',
            ]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'country_code' => 'required|string|max:6',
            'mobile' => 'required|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'city' => 'required|string|max:100',
            'account_type' => ['required', Rule::in(array_keys(config('account.account_types', [])))],
            'consent' => 'accepted',
        ]);

        try {
            $phone = $this->phones->normalize($validated['country_code'], $validated['mobile']);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['mobile' => $e->getMessage()]);
        }

        $whatsappInput = $validated['whatsapp'] ?? null;
        $whatsappNational = $whatsappInput ?: $phone['national'];
        $whatsappE164 = $phone['e164'];
        if ($whatsappInput) {
            try {
                $whatsappE164 = $this->phones->normalize($validated['country_code'], $whatsappInput)['e164'];
            } catch (InvalidArgumentException) {
                return back()->withInput()->withErrors(['whatsapp' => 'Enter a valid WhatsApp number.']);
            }
        }

        if (User::where('email', $validated['email'])->whereNotNull('phone_verified_at')->exists()) {
            return back()->withInput()->withErrors([
                'email' => 'An account with this email already exists. Try signing in.',
            ]);
        }

        if (User::where('mobile', $phone['national'])
            ->where('mobile_country_code', $phone['country_code'])
            ->whereNotNull('phone_verified_at')
            ->exists()) {
            return back()->withInput()->withErrors([
                'mobile' => 'This mobile number is already registered. Try signing in.',
            ]);
        }

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'country_code' => $phone['country_code'],
            'mobile' => $phone['national'],
            'whatsapp' => preg_replace('/\D/', '', $whatsappNational) ?: $phone['national'],
            'city' => $validated['city'],
            'account_type' => $validated['account_type'],
        ];

        try {
            $record = $this->otp->send($whatsappE164, 'register', $payload, $request->ip());
        } catch (WhatsAppNotConfiguredException|WhatsAppDeliveryException|\RuntimeException $e) {
            return $this->otpErrorResponse($e);
        }

        $this->formProtection->hitRateLimiters($request, 'account_register');

        $request->session()->put('account_register_password', $validated['password']);
        $this->storePendingVerification($request, $record, $phone);

        return redirect()->route('account.register')
            ->with('status', config('account.copy.otp_sent_generic'));
    }

    public function showForgot()
    {
        return view('account.forgot', [
            'providerReady' => $this->otp->providerConfigured(),
            'countryCodes' => config('account.country_codes', []),
        ]);
    }

    public function sendForgotOtp(Request $request)
    {
        return $this->dispatchOtp($request, 'forgot');
    }

    public function showVerifyOtp(Request $request)
    {
        $record = $this->pendingVerification($request);
        if (! $record) {
            return redirect()->route('account.login')
                ->with('info', 'Start by requesting a verification code.');
        }

        // Registration OTP stays on the create-account form (single-column flow).
        if ($record->purpose === 'register') {
            return redirect()->route('account.register');
        }

        return view('account.verify-otp', [
            'maskedMobile' => $this->phones->maskE164($record->mobile_e164),
            'purpose' => $record->purpose,
            'resendSeconds' => $this->otp->secondsUntilResend($record->mobile_e164),
            'canResend' => $this->otp->canResend($record->mobile_e164),
            'providerReady' => $this->otp->providerConfigured(),
        ]);
    }

    public function verifyOtp(Request $request)
    {
        if ($response = $this->guardOtpForm($request, 'account_verify_otp')) {
            return $response;
        }

        $record = $this->pendingVerification($request);
        if (! $record) {
            return redirect()->route('account.login');
        }

        $validated = $request->validate([
            'otp' => 'required|string|size:' . config('account.otp.length', 6),
        ]);

        if (! $this->otp->verify($record, $validated['otp'])) {
            $record->refresh();
            $this->formProtection->hitRateLimiters($request, 'account_verify_otp');

            $otpFail = back()->withErrors(['otp' => config('account.copy.failure')]);
            if ($record->purpose === 'register') {
                return $otpFail->withInput();
            }

            return $otpFail;
        }

        try {
            $user = $this->resolveUserAfterVerification($record);
        } catch (\RuntimeException $e) {
            $fail = back()->withErrors(['otp' => 'Unable to complete registration. Please try again or contact the studio.']);

            return $record->purpose === 'register' ? $fail->withInput() : $fail;
        }

        if (! $user->is_active) {
            return back()->withErrors(['otp' => 'This account has been disabled. Contact the studio for assistance.']);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('account_pending_verification_id');
        $request->session()->forget('account_register_password');

        return redirect()->intended(route('account'))
            ->with('success', config('account.copy.success'));
    }

    public function resendOtp(Request $request)
    {
        $record = $this->pendingVerification($request);
        if (! $record) {
            return redirect()->route('account.login');
        }

        $formKey = match ($record->purpose) {
            'register' => 'account_register',
            'forgot' => 'account_forgot_otp',
            default => 'account_login_otp',
        };

        if ($response = $this->guardOtpForm($request, $formKey)) {
            return $response;
        }

        if (! $this->otp->canResend($record->mobile_e164)) {
            return back()->withErrors(['otp' => 'Please wait before requesting another code.']);
        }

        try {
            $newRecord = $this->otp->resend($record, $request->ip());
        } catch (WhatsAppNotConfiguredException|WhatsAppDeliveryException|\RuntimeException $e) {
            return $this->otpErrorResponse($e);
        }

        $this->formProtection->hitRateLimiters($request, $formKey);

        $request->session()->put('account_pending_verification_id', $newRecord->id);

        if ($newRecord->purpose === 'register') {
            return redirect()->route('account.register')
                ->with('status', config('account.copy.otp_sent_generic'));
        }

        return back()->with('status', config('account.copy.otp_sent_generic'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('account.login')
            ->with('success', 'You have been signed out.');
    }

    private function dispatchOtp(Request $request, string $purpose)
    {
        $formKey = $purpose === 'forgot' ? 'account_forgot_otp' : 'account_login_otp';

        if ($response = $this->guardOtpForm($request, $formKey)) {
            return $response;
        }

        if (! $this->otp->providerConfigured()) {
            return back()->withInput()->withErrors([
                'mobile' => 'WhatsApp verification is not available yet. Please contact the studio.',
            ]);
        }

        $validated = $request->validate([
            'country_code' => 'required|string|max:6',
            'mobile' => 'required|string|max:20',
        ]);

        try {
            $phone = $this->phones->normalize($validated['country_code'], $validated['mobile']);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['mobile' => $e->getMessage()]);
        }

        $user = User::query()
            ->where('mobile', $phone['national'])
            ->where('mobile_country_code', $phone['country_code'])
            ->where('is_admin', false)
            ->whereNotNull('phone_verified_at')
            ->first();

        if ($user) {
            try {
                $record = $this->otp->send($phone['e164'], $purpose, null, $request->ip());
                $this->storePendingVerification($request, $record, $phone);
                $this->formProtection->hitRateLimiters($request, $formKey);
            } catch (WhatsAppNotConfiguredException|WhatsAppDeliveryException|\RuntimeException $e) {
                Log::warning('Login OTP send failed', ['mobile_e164' => $this->phones->maskE164($phone['e164']), 'error' => $e->getMessage()]);

                return $this->otpErrorResponse($e);
            }

            return redirect()->route('account.verify')
                ->with('status', config('account.copy.otp_sent_generic'));
        }

        Log::info('OTP requested for unknown or unverified mobile', [
            'mobile_e164' => $this->phones->maskE164($phone['e164']),
            'purpose' => $purpose,
        ]);

        return back()->with('status', config('account.copy.otp_sent_generic'));
    }

    private function resolveUserAfterVerification(WhatsappOtpVerification $record): User
    {
        $phoneDigits = $record->mobile_e164;

        if ($record->purpose === 'register') {
            $payload = $record->payload ?? [];

            $user = User::query()
                ->where('mobile', $payload['mobile'] ?? '')
                ->where('mobile_country_code', $payload['country_code'] ?? '+91')
                ->where('is_admin', false)
                ->first();

            $password = session('account_register_password') ?: Str::password(32);

            $attributes = [
                'name' => $payload['name'],
                'email' => $payload['email'],
                'mobile_country_code' => $payload['country_code'],
                'mobile' => $payload['mobile'],
                'whatsapp' => $payload['whatsapp'] ?? $payload['mobile'],
                'city' => $payload['city'],
                'account_type' => $payload['account_type'] ?? 'customer',
                'phone_verified_at' => now(),
                'password' => $password,
            ];

            if ($user) {
                if (User::where('email', $payload['email'])->where('id', '!=', $user->id)->exists()) {
                    throw new \RuntimeException('Email already in use.');
                }
                $user->update($attributes);
            } else {
                if (User::where('email', $payload['email'])->exists()) {
                    throw new \RuntimeException('Email already in use.');
                }
                $user = User::create(array_merge($attributes, ['is_admin' => false]));
            }

            return $user;
        }

        $user = User::query()
            ->where('is_admin', false)
            ->whereNotNull('phone_verified_at')
            ->get()
            ->first(fn (User $u) => $u->mobileE164() === $phoneDigits);

        if (! $user) {
            abort(403);
        }

        return $user;
    }

    private function storePendingVerification(Request $request, WhatsappOtpVerification $record, array $phone): void
    {
        $request->session()->put('account_pending_verification_id', $record->id);
        $request->session()->put('account_pending_mobile_display', $phone['display']);
    }

    private function pendingVerification(Request $request): ?WhatsappOtpVerification
    {
        $id = $request->session()->get('account_pending_verification_id');
        if (! $id) {
            return null;
        }

        return WhatsappOtpVerification::query()->find($id);
    }

    private function authView(string $tab)
    {
        $registerPending = null;
        if ($tab === 'register') {
            $pending = $this->pendingVerification(request());
            if ($pending && $pending->purpose === 'register' && ! $pending->isVerified()) {
                $registerPending = $pending;
            }
        }

        return view('account.auth', [
            'tab' => $tab,
            'providerReady' => $this->otp->providerConfigured(),
            'countryCodes' => config('account.country_codes', []),
            'accountTypes' => config('account.account_types', []),
            'registerPending' => $registerPending,
            'registerMaskedMobile' => $registerPending
                ? $this->phones->maskE164($registerPending->mobile_e164)
                : null,
            'registerCanResend' => (bool) ($registerPending && $this->otp->canResend($registerPending->mobile_e164)),
            'registerResendSeconds' => $registerPending
                ? $this->otp->secondsUntilResend($registerPending->mobile_e164)
                : 0,
        ]);
    }

    private function otpErrorResponse(\Throwable $e)
    {
        $message = $e instanceof WhatsAppNotConfiguredException
            ? 'WhatsApp verification is not available yet. Please contact the studio.'
            : 'Unable to send verification code right now. Please try again shortly.';

        return back()->withInput()->withErrors(['mobile' => $message]);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|null
     */
    private function guardOtpForm(Request $request, string $formKey)
    {
        $check = $this->formProtection->validateSubmission($request, $formKey, false);

        if (! $check['reject']) {
            return null;
        }

        if ($check['rate_limited']) {
            return response(config('form_protection.messages.rate_limited'), 429);
        }

        return back()
            ->withInput($request->except(['password', 'password_confirmation', 'cf-turnstile-response']))
            ->withErrors(['form' => $check['message']]);
    }
}
