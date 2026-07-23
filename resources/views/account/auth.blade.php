@extends('layouts.store')

@php
    $activeTab = $tab ?? (request()->routeIs('account.register') ? 'register' : 'login');
    $loginMethod = old('login_method', session('login_method', 'email'));
    $mobileLoginMode = old('mobile_login_mode', session('mobile_login_mode', 'otp'));
@endphp

@section('title', ($activeTab === 'register' ? 'Create account' : 'Sign in') . ' — Vyomika Atelier')

@section('content')
<x-account-auth-layout>
    <div class="am-account-card am-account-theme">
        <nav class="am-account-card__tabs" aria-label="Account">
            <a href="{{ route('account.login') }}"
               class="am-account-card__tab {{ $activeTab === 'login' ? 'is-active' : '' }}"
               @if($activeTab === 'login') aria-current="page" @endif>
                Sign in
            </a>
            <a href="{{ route('account.register') }}"
               class="am-account-card__tab {{ $activeTab === 'register' ? 'is-active' : '' }}"
               @if($activeTab === 'register') aria-current="page" @endif>
                Create account
            </a>
        </nav>

        @include('partials.am-account-alerts')

        @unless($providerReady)
        <p class="am-account-notice am-account-notice--warning" role="status">
            WhatsApp OTP is not configured yet. Mobile OTP signup and sign-in are unavailable until provider setup is complete.
        </p>
        @endunless

        @if($activeTab === 'login')
        <div class="am-account-card__panel" id="account-login-panel">
            <div class="am-account-card__method {{ $loginMethod === 'email' ? '' : 'is-hidden' }}" data-login-panel="email">
                <form action="{{ route('account.login.email') }}" method="POST" class="am-account-card__form">
                    @csrf
                    <input type="hidden" name="login_method" value="email">
                    <div class="am-account-card__field">
                        <label for="login-email">Email</label>
                        <input type="email" name="email" id="login-email" value="{{ old('email') }}" required class="am-input" autocomplete="email">
                    </div>
                    <div class="am-account-card__field">
                        <label for="login-password">Password</label>
                        <input type="password" name="password" id="login-password" required class="am-input" autocomplete="current-password">
                    </div>
                    <button type="submit" class="am-account-card__submit">
                        <span>Sign in</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </button>
                </form>
            </div>

            <div class="am-account-card__method {{ $loginMethod === 'mobile' && $mobileLoginMode === 'otp' ? '' : 'is-hidden' }}" data-login-panel="mobile-otp">
                <form action="{{ route('account.login.send') }}" method="POST" class="am-account-card__form">
                    @csrf
                    <input type="hidden" name="login_method" value="mobile">
                    <input type="hidden" name="mobile_login_mode" value="otp">
                    @include('partials.am-account-phone-fields', ['countryCodes' => $countryCodes, 'fieldPrefix' => 'login-otp'])
                    <x-form-protection-fields form-key="account_login_otp" :show-intent="false" />
                    <button type="submit" class="am-account-card__submit" @disabled(! $providerReady)>
                        <span>Send WhatsApp OTP</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </button>
                </form>
            </div>

            <div class="am-account-card__method {{ $loginMethod === 'mobile' && $mobileLoginMode === 'password' ? '' : 'is-hidden' }}" data-login-panel="mobile-password">
                <form action="{{ route('account.login.mobile') }}" method="POST" class="am-account-card__form">
                    @csrf
                    <input type="hidden" name="login_method" value="mobile">
                    <input type="hidden" name="mobile_login_mode" value="password">
                    @include('partials.am-account-phone-fields', ['countryCodes' => $countryCodes, 'fieldPrefix' => 'login-mobile'])
                    <div class="am-account-card__field">
                        <label for="login-mobile-password">Password</label>
                        <input type="password" name="password" id="login-mobile-password" required class="am-input" autocomplete="current-password">
                    </div>
                    <button type="submit" class="am-account-card__submit">
                        <span>Sign in</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </button>
                </form>
            </div>

            <p class="am-account-card__footer-link">
                <a href="{{ route('account.forgot') }}">Forgot password or access?</a>
            </p>

            <p class="am-account-card__switch" data-account-login-switch>
                @if($loginMethod === 'email')
                <button type="button" data-login-panel-target="mobile-otp">Sign in with WhatsApp OTP</button>
                <span aria-hidden="true">·</span>
                <button type="button" data-login-panel-target="mobile-password">Sign in with mobile &amp; password</button>
                @elseif($loginMethod === 'mobile' && $mobileLoginMode === 'otp')
                <button type="button" data-login-panel-target="email">Sign in with email</button>
                <span aria-hidden="true">·</span>
                <button type="button" data-login-panel-target="mobile-password">Sign in with mobile &amp; password</button>
                @else
                <button type="button" data-login-panel-target="email">Sign in with email</button>
                <span aria-hidden="true">·</span>
                <button type="button" data-login-panel-target="mobile-otp">Sign in with WhatsApp OTP</button>
                @endif
            </p>
        </div>
        @else
        @php
            $awaitingRegisterOtp = ! empty($registerPending);
            $registerOtpVerified = $registerOtpVerified ?? false;
            $registerDetails = $registerDetails ?? [];
            $registerLocked = $awaitingRegisterOtp;
            $registerFieldValues = $registerLocked ? $registerDetails : [];
        @endphp
        <div class="am-account-card__panel" id="account-register-panel">
            <div class="am-account-signup">
                <div class="am-account-signup__details">
                    @if($registerLocked)
                    <div class="am-account-card__field">
                        <label for="register-name-locked">Full name</label>
                        <input type="text" id="register-name-locked" value="{{ $registerFieldValues['name'] ?? '' }}" class="am-input am-input--underline" readonly>
                    </div>
                    <div class="am-account-card__field">
                        <label for="register-email-locked">Email</label>
                        <input type="email" id="register-email-locked" value="{{ $registerFieldValues['email'] ?? '' }}" class="am-input am-input--underline" readonly>
                    </div>
                    <div class="am-account-card__field">
                        <label for="register-city-locked">City</label>
                        <input type="text" id="register-city-locked" value="{{ $registerFieldValues['city'] ?? '' }}" class="am-input am-input--underline" readonly>
                    </div>
                    <div class="am-account-card__field">
                        <label for="register-account_type-locked">Account type</label>
                        <input type="text" id="register-account_type-locked" value="{{ $accountTypes[$registerFieldValues['account_type'] ?? ''] ?? ($registerFieldValues['account_type'] ?? '') }}" class="am-input am-input--underline" readonly>
                    </div>
                    <div class="am-account-card__field">
                        <label for="register-mobile-locked">Phone number (WhatsApp)</label>
                        <input type="text" id="register-mobile-locked" value="{{ $registerMaskedMobile }}" class="am-input am-input--underline" readonly>
                    </div>
                    @else
                    <form action="{{ route('account.register.send') }}" method="POST" class="am-account-card__form am-account-signup__form" id="account-register-send-form">
                        @csrf
                        <div class="am-account-card__field">
                            <label for="register-name">Full name</label>
                            <input type="text" name="name" id="register-name" value="{{ old('name') }}" required class="am-input am-input--underline" autocomplete="name" placeholder="Your name">
                        </div>
                        <div class="am-account-card__field">
                            <label for="register-email">Email</label>
                            <input type="email" name="email" id="register-email" value="{{ old('email') }}" required class="am-input am-input--underline" autocomplete="email" placeholder="you@email.com">
                        </div>
                        <div class="am-account-card__field">
                            <label for="register-city">City</label>
                            <input type="text" name="city" id="register-city" value="{{ old('city') }}" required class="am-input am-input--underline" autocomplete="address-level2" placeholder="City">
                        </div>
                        <div class="am-account-card__field">
                            <label for="register-account_type">Account type</label>
                            <select name="account_type" id="register-account_type" class="am-input am-input--select am-input--underline" required>
                                <option value="">Select type</option>
                                @foreach($accountTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('account_type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="am-account-card__field">
                            <label for="register-mobile">Phone number (WhatsApp)</label>
                            @include('partials.am-account-phone-fields', ['countryCodes' => $countryCodes, 'fieldPrefix' => 'register'])
                            <p class="am-account-card__hint">Verification code is sent to this WhatsApp number</p>
                        </div>
                        <x-form-protection-fields form-key="account_register" :show-intent="false" />
                        <button type="submit" class="am-account-card__submit am-account-card__submit--secondary" @disabled(! $providerReady)>
                            <span>Send verification code</span>
                        </button>
                    </form>
                    @endif
                </div>

                @if($awaitingRegisterOtp && ! $registerOtpVerified)
                <section class="am-account-signup__verify" aria-labelledby="register-verify-heading">
                    <h2 class="am-account-signup__heading" id="register-verify-heading">Mobile verification</h2>
                    <p class="am-account-signup__status" role="status">Code sent to WhatsApp. Enter the verification code below.</p>

                    <form action="{{ route('account.verify.submit') }}" method="POST" class="am-account-card__form" id="account-otp-form">
                        @csrf
                        <div class="am-account-card__field">
                            <label for="otp-combined">Verification code</label>
                            @include('partials.am-account-otp-inputs')
                            <input type="hidden" name="otp" id="otp-combined" value="">
                        </div>
                        <x-form-protection-fields form-key="account_verify_otp" :show-intent="false" />
                        <button type="submit" class="am-account-card__submit">
                            <span>Verify OTP</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </button>
                    </form>

                    <div class="am-account-verify__actions">
                        @if($registerCanResend && $providerReady)
                        <form action="{{ route('account.resend') }}" method="POST">
                            @csrf
                            <x-form-protection-fields form-key="account_register" :show-intent="false" />
                            <button type="submit" class="am-account-card__link-btn">{{ config('account.copy.resend_otp') }}</button>
                        </form>
                        @else
                        <p class="am-account-verify__countdown" id="otp-resend-countdown" data-seconds="{{ $registerResendSeconds }}">
                            Resend available in <span>{{ $registerResendSeconds }}</span>s
                        </p>
                        @endif
                        <a href="{{ route('account.register', ['change_number' => 1]) }}" class="am-account-verify__change">
                            Change WhatsApp number
                        </a>
                    </div>
                </section>
                @endif

                @if($awaitingRegisterOtp && $registerOtpVerified)
                <section class="am-account-signup__password" aria-labelledby="register-password-heading">
                    <h2 class="am-account-signup__heading" id="register-password-heading">Create password</h2>
                    <p class="am-account-signup__status" role="status">WhatsApp number verified. Set your password to finish.</p>

                    <form action="{{ route('account.register.send') }}" method="POST" class="am-account-card__form am-account-signup__complete-form">
                        @csrf
                        <input type="hidden" name="register_step" value="complete">
                        <div class="am-account-card__field">
                            <label for="register-password">Password</label>
                            <input type="password" name="password" id="register-password" required class="am-input am-input--underline" autocomplete="new-password" minlength="8" placeholder="Min. 8 characters">
                        </div>
                        <label class="am-account-consent">
                            <input type="checkbox" name="consent" value="1" @checked(old('consent')) required>
                            <span>I agree to the <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener">Terms &amp; Conditions</a> and <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>.</span>
                        </label>
                        <x-form-protection-fields form-key="account_register" :show-intent="false" />
                        <button type="submit" class="am-account-card__submit">
                            <span>Create account</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </button>
                    </form>
                </section>
                @endif
            </div>
        </div>
        @endif
    </div>
</x-account-auth-layout>
@endsection

@push('scripts')
<script src="{{ asset('js/account-auth.js') }}" defer></script>
@if(! empty($registerPending) && empty($registerOtpVerified))
<script src="{{ asset('js/account-otp.js') }}" defer></script>
@endif
@endpush
