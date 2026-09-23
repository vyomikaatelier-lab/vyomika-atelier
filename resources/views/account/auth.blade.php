@extends('layouts.store')

@php
    $activeTab = $tab ?? (request()->routeIs('account.register') ? 'register' : 'login');
    $pageTitle = $activeTab === 'register' ? 'Create Account' : 'Sign In';
    $pageSubtitle = $activeTab === 'register'
        ? 'Create your account with your name, email, and password.'
        : 'Welcome back. Sign in with your email and password.';
    $purchaseIntent = $purchaseIntent ?? false;
    $socialProviders = $socialProviders ?? ['google' => false, 'apple' => false];
    $countryCodes = $countryCodes ?? [];
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

        @if($purchaseIntent)
        <div class="am-account-notice" role="status">
            <p>Sign in or create an account to complete your purchase.</p>
            <p class="am-account-card__footer-links">
                <a href="{{ route('account.login') }}">Login</a>
                <a href="{{ route('account.register') }}">Create Account</a>
            </p>
        </div>
        @endif

        @if($activeTab === 'login')
        <div class="am-account-card__panel" id="account-login-panel">
            <form action="{{ route('account.login.email') }}" method="POST" class="am-account-card__form">
                @csrf
                <div class="am-account-card__field">
                    <label for="login-email">Email</label>
                    <div class="am-account-field-input">
                        @include('partials.am-account-field-icon', ['icon' => 'email'])
                        <input type="email" name="email" id="login-email" value="{{ old('email') }}" required class="am-input" autocomplete="username" placeholder="you@email.com">
                    </div>
                </div>
                <div class="am-account-card__field">
                    <label for="login-password">Password</label>
                    <div class="am-account-field-input">
                        @include('partials.am-account-field-icon', ['icon' => 'password'])
                        <input type="password" name="password" id="login-password" required class="am-input" autocomplete="current-password" placeholder="Password">
                    </div>
                </div>
                <label class="am-account-consent">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    <span>Remember me</span>
                </label>
                <button type="submit" class="am-account-card__submit">
                    <span>Log in</span>
                </button>
            </form>

            <div class="am-account-card__footer-links">
                <a href="{{ route('account.forgot') }}">Forgot password?</a>
            </div>

            @include('partials.am-account-social-buttons', ['mode' => 'login'])

            <a href="{{ route('account.register') }}" class="am-account-card__cta-secondary">Create an account</a>
        </div>
        @else
        <div class="am-account-card__panel" id="account-register-panel">
            <form action="{{ route('account.register.send') }}" method="POST" class="am-account-card__form" id="account-register-form">
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
                    <label for="register-mobile">Mobile number (optional)</label>
                    <div class="am-account-field-input am-account-field-input--phone">
                        @include('partials.am-account-field-icon', ['icon' => 'phone'])
                        @include('partials.am-account-phone-fields', ['countryCodes' => $countryCodes, 'fieldPrefix' => 'register', 'required' => false])
                    </div>
                    <p class="am-account-card__hint">Used for delivery contact. A mobile number is required at checkout.</p>
                </div>
                <button type="submit" class="am-account-card__submit">
                    <span>Create Account</span>
                </button>
            </form>

            <div class="am-account-card__divider" role="presentation">
                <span>Already have an account?</span>
            </div>
            <a href="{{ route('account.login') }}" class="am-account-card__cta-secondary">Sign in</a>
        </div>
        @endif
    </div>
</x-account-auth-layout>
@endsection
