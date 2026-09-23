@extends('layouts.store')

@section('title', 'Set a new password — Vyomika Atelier')

@section('content')
<x-account-auth-layout>
    <div class="am-account-card am-account-theme">
        <header class="am-account-card__header">
            <h1 class="am-account-card__hero-title">Set a new password</h1>
            <p class="am-account-card__subtitle">Choose a new password for your account.</p>
        </header>

        @include('partials.am-account-alerts')

        <form action="{{ route('account.password.update') }}" method="POST" class="am-account-card__form">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="am-account-card__field">
                <label for="reset-email">Email</label>
                <div class="am-account-field-input">
                    @include('partials.am-account-field-icon', ['icon' => 'email'])
                    <input type="email" name="email" id="reset-email" value="{{ old('email', $email) }}" required class="am-input" autocomplete="email">
                </div>
            </div>
            <div class="am-account-card__field">
                <label for="reset-password">New password</label>
                <div class="am-account-field-input">
                    @include('partials.am-account-field-icon', ['icon' => 'password'])
                    <input type="password" name="password" id="reset-password" required class="am-input" autocomplete="new-password" minlength="8" placeholder="New password">
                </div>
            </div>
            <div class="am-account-card__field">
                <label for="reset-password-confirmation">Confirm password</label>
                <div class="am-account-field-input">
                    @include('partials.am-account-field-icon', ['icon' => 'password'])
                    <input type="password" name="password_confirmation" id="reset-password-confirmation" required class="am-input" autocomplete="new-password" minlength="8" placeholder="Confirm password">
                </div>
            </div>
            <button type="submit" class="am-account-card__submit">
                <span>Update password</span>
            </button>
        </form>

        <p class="am-account-card__footer-link">
            <a href="{{ route('account.login') }}">Back to sign in</a>
        </p>
    </div>
</x-account-auth-layout>
@endsection
