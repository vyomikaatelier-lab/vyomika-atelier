@extends('layouts.store')

@php
    $activeTab = $tab ?? (request()->routeIs('account.register') ? 'register' : 'login');
    $loginMethod = old('login_method', session('login_method', 'email'));
    $mobileLoginMode = old('mobile_login_mode', session('mobile_login_mode', 'otp'));
@endphp

@section('title', ($activeTab === 'register' ? 'Create account' : 'Sign in') . ' — Vyomika Atelier LLP')

@section('content')
<x-account-auth-layout>
    <div class="am-account-card">
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
        <div class="am-account-card__panel" id="account-register-panel">
            <form action="{{ route('account.register.send') }}" method="POST" class="am-account-card__form">
                @csrf
                <div class="am-account-card__field">
                    <label for="register-name">Full name</label>
                    <input type="text" name="name" id="register-name" value="{{ old('name') }}" required class="am-input" autocomplete="name">
                </div>
                <div class="am-account-card__field">
                    <label for="register-email">Email</label>
                    <input type="email" name="email" id="register-email" value="{{ old('email') }}" required class="am-input" autocomplete="email">
                </div>
                <div class="am-account-card__field">
                    <label for="register-password">Password</label>
                    <input type="password" name="password" id="register-password" required class="am-input" autocomplete="new-password" minlength="8">
                </div>
                <div class="am-account-card__field">
                    <label for="register-password-confirmation">Confirm password</label>
                    <input type="password" name="password_confirmation" id="register-password-confirmation" required class="am-input" autocomplete="new-password" minlength="8">
                </div>
                <div class="am-account-card__columns">
                    <div class="am-account-card__field">
                        <label for="register-country_code">Mobile</label>
                        @include('partials.am-account-phone-fields', ['countryCodes' => $countryCodes, 'fieldPrefix' => 'register', 'stacked' => true])
                    </div>
                    <div class="am-account-card__field">
                        <label for="register-whatsapp">WhatsApp</label>
                        <input type="tel" name="whatsapp" id="register-whatsapp" value="{{ old('whatsapp') }}" placeholder="Same as mobile if blank" class="am-input" inputmode="numeric" autocomplete="tel">
                        <p class="am-account-card__hint">OTP is sent to WhatsApp for verification</p>
                    </div>
                </div>
                <div class="am-account-card__field">
                    <label for="register-city">City</label>
                    <input type="text" name="city" id="register-city" value="{{ old('city') }}" required class="am-input" autocomplete="address-level2">
                </div>
                <div class="am-account-card__field">
                    <label for="register-account_type">Account type</label>
                    <select name="account_type" id="register-account_type" class="am-input am-input--select" required>
                        <option value="">Select type</option>
                        @foreach($accountTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('account_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="am-account-consent">
                    <input type="checkbox" name="consent" value="1" @checked(old('consent')) required>
                    <span>I agree to the <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener">Terms &amp; Conditions</a> and <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>.</span>
                </label>
                <button type="submit" class="am-account-card__submit" @disabled(! $providerReady)>
                    <span>Create account</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </form>
        </div>
        @endif
    </div>
</x-account-auth-layout>
@endsection

@push('scripts')
<script src="{{ asset('js/account-auth.js') }}" defer></script>
@endpush
