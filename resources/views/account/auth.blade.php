@extends('layouts.store')

@php
    $activeTab = $tab ?? (request()->routeIs('account.register') ? 'register' : 'login');
    $pageTitle = $activeTab === 'register' ? 'Create Account' : 'Sign In';
    $pageSubtitle = $activeTab === 'register'
        ? 'Choose Apple, Google, or email with WhatsApp verification to join Vyomika Atelier.'
        : 'Welcome back. Sign in with email or mobile and password.';
@endphp

@section('title', ($activeTab === 'register' ? 'Create account' : 'Sign in') . ' — Vyomika Atelier')

@section('content')
<x-account-auth-layout>
    <div class="am-account-card am-account-theme">
        <header class="am-account-card__header">
            <h1 class="am-account-card__hero-title">{{ $pageTitle }}</h1>
            <p class="am-account-card__subtitle">{{ $pageSubtitle }}</p>
        </header>

        @include('partials.am-account-alerts')

        @unless($providerReady)
        <p class="am-account-notice am-account-notice--warning" role="status">
            WhatsApp OTP is not configured yet. Mobile OTP signup and sign-in are unavailable until provider setup is complete.
        </p>
        @endunless

        @if($activeTab === 'login')
        @php
            $showOtpLogin = $showOtpLogin ?? request()->boolean('otp');
            $socialProviders = $socialProviders ?? ['google' => false, 'apple' => false];
        @endphp
        <div class="am-account-card__panel" id="account-login-panel">
            @if($showOtpLogin)
            <form action="{{ route('account.login.send') }}" method="POST" class="am-account-card__form">
                @csrf
                <input type="hidden" name="login_method" value="mobile">
                <input type="hidden" name="mobile_login_mode" value="otp">
                <div class="am-account-card__field">
                    <label for="login-otp-mobile">Mobile number (WhatsApp)</label>
                    <div class="am-account-field-input am-account-field-input--phone">
                        @include('partials.am-account-field-icon', ['icon' => 'phone'])
                        @include('partials.am-account-phone-fields', ['countryCodes' => $countryCodes, 'fieldPrefix' => 'login-otp'])
                    </div>
                </div>
                <x-form-protection-fields form-key="account_login_otp" :show-intent="false" />
                <button type="submit" class="am-account-card__submit" @disabled(! $providerReady)>
                    <span>Send OTP</span>
                </button>
            </form>
            <p class="am-account-card__footer-link">
                <a href="{{ route('account.login') }}">Sign in with password</a>
            </p>
            @else
            <form action="{{ route('account.login.email') }}" method="POST" class="am-account-card__form">
                @csrf
                <div class="am-account-card__field">
                    <label for="login-identifier" class="am-sr-only">Email or mobile number</label>
                    <div class="am-account-field-input">
                        @include('partials.am-account-field-icon', ['icon' => 'email'])
                        <input type="text" name="login" id="login-identifier" value="{{ old('login', old('email')) }}" required class="am-input" autocomplete="username" placeholder="Email address or mobile number">
                    </div>
                </div>
                <div class="am-account-card__field">
                    <label for="login-password" class="am-sr-only">Password</label>
                    <div class="am-account-field-input">
                        @include('partials.am-account-field-icon', ['icon' => 'password'])
                        <input type="password" name="password" id="login-password" required class="am-input" autocomplete="current-password" placeholder="Password">
                    </div>
                </div>
                <button type="submit" class="am-account-card__submit">
                    <span>Log in</span>
                </button>
            </form>

            <div class="am-account-card__footer-links">
                <a href="{{ route('account.forgot') }}">Forgot password?</a>
                <a href="{{ route('account.login', ['otp' => 1]) }}">Sign in with OTP</a>
            </div>

            @include('partials.am-account-social-buttons', ['mode' => 'login'])

            <a href="{{ route('account.register') }}" class="am-account-card__cta-secondary">Create an account</a>
            @endif
        </div>
        @else
        @php
            $awaitingRegisterOtp = ! empty($registerPending);
            $registerOtpVerified = $registerOtpVerified ?? false;
            $registerDetails = $registerDetails ?? [];
            $registerLocked = $awaitingRegisterOtp;
            $registerFieldValues = $registerLocked ? $registerDetails : [];
            $socialProviders = $socialProviders ?? ['google' => false, 'apple' => false];
        @endphp
        <div class="am-account-card__panel" id="account-register-panel">
            <div class="am-account-signup">
                @unless($registerLocked)
                @include('partials.am-account-social-buttons')

                <div class="am-account-card__divider" role="presentation">
                    <span>or continue with email</span>
                </div>
                @endunless

                <div class="am-account-signup__details">
                    @if($registerLocked)
                    <div class="am-account-card__field">
                        <label for="register-name-locked">Full name</label>
                        <div class="am-account-field-input am-account-field-input--readonly">
                            @include('partials.am-account-field-icon', ['icon' => 'user'])
                            <input type="text" id="register-name-locked" value="{{ $registerFieldValues['name'] ?? '' }}" class="am-input" readonly>
                        </div>
                    </div>
                    <div class="am-account-card__field">
                        <label for="register-email-locked">Email</label>
                        <div class="am-account-field-input am-account-field-input--readonly">
                            @include('partials.am-account-field-icon', ['icon' => 'email'])
                            <input type="email" id="register-email-locked" value="{{ $registerFieldValues['email'] ?? '' }}" class="am-input" readonly>
                        </div>
                    </div>
                    <div class="am-account-card__field">
                        <label for="register-password-locked">Password</label>
                        <div class="am-account-field-input am-account-field-input--readonly">
                            @include('partials.am-account-field-icon', ['icon' => 'password'])
                            <input type="password" id="register-password-locked" value="••••••••" class="am-input" readonly>
                        </div>
                    </div>
                    <div class="am-account-card__field">
                        <label for="register-city-locked">City</label>
                        <div class="am-account-field-input am-account-field-input--readonly">
                            @include('partials.am-account-field-icon', ['icon' => 'location'])
                            <input type="text" id="register-city-locked" value="{{ $registerFieldValues['city'] ?? '' }}" class="am-input" readonly>
                        </div>
                    </div>
                    <div class="am-account-card__field">
                        <label for="register-account_type-locked">Account type</label>
                        <div class="am-account-field-input am-account-field-input--readonly">
                            @include('partials.am-account-field-icon', ['icon' => 'badge'])
                            <input type="text" id="register-account_type-locked" value="{{ $accountTypes[$registerFieldValues['account_type'] ?? ''] ?? ($registerFieldValues['account_type'] ?? '') }}" class="am-input" readonly>
                        </div>
                    </div>
                    <div class="am-account-card__field">
                        <label for="register-mobile-locked">Mobile number (WhatsApp)</label>
                        <div class="am-account-field-input am-account-field-input--readonly">
                            @include('partials.am-account-field-icon', ['icon' => 'phone'])
                            <input type="text" id="register-mobile-locked" value="{{ $registerMaskedMobile }}" class="am-input" readonly>
                        </div>
                    </div>
                    @else
                    <form action="{{ route('account.register.send') }}" method="POST" class="am-account-card__form am-account-signup__form" id="account-register-send-form">
                        @csrf
                        <div class="am-account-card__field">
                            <label for="register-name">Full name</label>
                            <div class="am-account-field-input">
                                @include('partials.am-account-field-icon', ['icon' => 'user'])
                                <input type="text" name="name" id="register-name" value="{{ old('name') }}" required class="am-input" autocomplete="name" placeholder="Your name">
                            </div>
                        </div>
                        <div class="am-account-card__field">
                            <label for="register-email">Email</label>
                            <div class="am-account-field-input">
                                @include('partials.am-account-field-icon', ['icon' => 'email'])
                                <input type="email" name="email" id="register-email" value="{{ old('email') }}" required class="am-input" autocomplete="email" placeholder="you@email.com">
                            </div>
                        </div>
                        <div class="am-account-card__field">
                            <label for="register-password">Password</label>
                            <div class="am-account-field-input">
                                @include('partials.am-account-field-icon', ['icon' => 'password'])
                                <input type="password" name="password" id="register-password" required class="am-input" autocomplete="new-password" minlength="8" placeholder="Password">
                            </div>
                        </div>
                        <div class="am-account-card__field">
                            <label for="register-password-confirmation">Confirm password</label>
                            <div class="am-account-field-input">
                                @include('partials.am-account-field-icon', ['icon' => 'password'])
                                <input type="password" name="password_confirmation" id="register-password-confirmation" required class="am-input" autocomplete="new-password" minlength="8" placeholder="Confirm password">
                            </div>
                        </div>
                        <div class="am-account-card__field">
                            <label for="register-city">City</label>
                            <div class="am-account-field-input">
                                @include('partials.am-account-field-icon', ['icon' => 'location'])
                                <input type="text" name="city" id="register-city" value="{{ old('city') }}" required class="am-input" autocomplete="address-level2" placeholder="City">
                            </div>
                        </div>
                        <div class="am-account-card__field">
                            <label for="register-account_type">Account type</label>
                            <div class="am-account-field-input am-account-field-input--select">
                                @include('partials.am-account-field-icon', ['icon' => 'badge'])
                                <select name="account_type" id="register-account_type" class="am-input am-input--select" required>
                                    <option value="">Select type</option>
                                    @foreach($accountTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('account_type') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="am-account-card__field">
                            <label for="register-mobile">Mobile number (WhatsApp)</label>
                            <div class="am-account-field-input am-account-field-input--phone">
                                @include('partials.am-account-field-icon', ['icon' => 'phone'])
                                @include('partials.am-account-phone-fields', ['countryCodes' => $countryCodes, 'fieldPrefix' => 'register'])
                            </div>
                            <div class="am-account-field__action-row">
                                <button type="submit" class="am-account-send-otp" @disabled(! $providerReady)>Send OTP</button>
                            </div>
                        </div>
                        <x-form-protection-fields form-key="account_register" :show-intent="false" />
                    </form>
                    @endif

                    <div class="am-account-signup__otp {{ $awaitingRegisterOtp ? 'is-active' : '' }} {{ $registerOtpVerified ? 'is-done' : '' }}">
                        <div class="am-account-card__field">
                            <label for="otp-combined">OTP</label>
                            @if($awaitingRegisterOtp && ! $registerOtpVerified)
                            <form action="{{ route('account.verify.submit') }}" method="POST" class="am-account-signup__otp-form" id="account-otp-form">
                                @csrf
                                @include('partials.am-account-otp-inputs')
                                <input type="hidden" name="otp" id="otp-combined" value="">
                                <x-form-protection-fields form-key="account_verify_otp" :show-intent="false" />
                                <button type="submit" class="am-account-card__submit am-account-card__submit--compact">
                                    <span>Verify OTP</span>
                                </button>
                            </form>
                            <div class="am-account-verify__actions">
                                @if($registerCanResend && $providerReady)
                                <form action="{{ route('account.resend') }}" method="POST">
                                    @csrf
                                    <x-form-protection-fields form-key="account_register" :show-intent="false" />
                                    <button type="submit" class="am-account-card__link-btn">{{ config('account.copy.resend_otp') }}</button>
                                </form>
                                @elseif($awaitingRegisterOtp)
                                <p class="am-account-verify__countdown" id="otp-resend-countdown" data-seconds="{{ $registerResendSeconds }}">
                                    Resend available in <span>{{ $registerResendSeconds }}</span>s
                                </p>
                                @endif
                                <a href="{{ route('account.register', ['change_number' => 1]) }}" class="am-account-verify__change">
                                    Change number
                                </a>
                            </div>
                            @elseif($registerOtpVerified)
                            <p class="am-account-signup__status am-account-signup__status--inline" role="status">WhatsApp number verified.</p>
                            @else
                            @include('partials.am-account-otp-inputs', ['disabled' => true])
                            <p class="am-account-card__hint">Send OTP to your mobile number to enable verification.</p>
                            @endif
                        </div>
                    </div>
                </div>

                @if($awaitingRegisterOtp && $registerOtpVerified)
                <section class="am-account-signup__complete" aria-labelledby="register-complete-heading">
                    <h2 class="am-account-signup__heading" id="register-complete-heading">Create account</h2>
                    <p class="am-account-signup__status" role="status">Your details and WhatsApp number are verified. Confirm to finish.</p>

                    <form action="{{ route('account.register.send') }}" method="POST" class="am-account-card__form am-account-signup__complete-form">
                        @csrf
                        <input type="hidden" name="register_step" value="complete">
                        <label class="am-account-consent">
                            <input type="checkbox" name="consent" value="1" @checked(old('consent')) required>
                            <span>I agree to the <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener">Terms &amp; Conditions</a> and <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>.</span>
                        </label>
                        <x-form-protection-fields form-key="account_register" :show-intent="false" />
                        <button type="submit" class="am-account-card__submit">
                            <span>Create account</span>
                        </button>
                    </form>
                </section>
                @endif
            </div>

            @unless($awaitingRegisterOtp ?? false)
            <div class="am-account-card__divider" role="presentation">
                <span>Already have an account?</span>
            </div>
            <a href="{{ route('account.login') }}" class="am-account-card__cta-secondary">Sign in</a>
            @else
            <p class="am-account-card__footer-link">
                <a href="{{ route('account.login') }}">Back to sign in</a>
            </p>
            @endunless
        </div>
        @endif
    </div>
</x-account-auth-layout>
@endsection

@push('scripts')
@if($activeTab === 'register' && ! empty($registerPending) && empty($registerOtpVerified))
<script src="{{ asset('js/account-otp.js') }}" defer></script>
@endif
@endpush
