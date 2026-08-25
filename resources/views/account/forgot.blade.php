@extends('layouts.store')

@section('title', 'Reset Password — Vyomika Atelier')

@section('content')
<x-account-auth-layout>
    <div class="am-account-card am-account-theme">
        <header class="am-account-card__header">
            <h1 class="am-account-card__hero-title">Reset password</h1>
            <p class="am-account-card__subtitle">Enter the email for your account. If it exists, we will send a reset link.</p>
        </header>

        @include('partials.am-account-alerts')

        <form action="{{ route('account.forgot.send') }}" method="POST" class="am-account-card__form">
            @csrf
            <div class="am-account-card__field">
                <label for="forgot-email">Email</label>
                <div class="am-account-field-input">
                    @include('partials.am-account-field-icon', ['icon' => 'email'])
                    <input type="email" name="email" id="forgot-email" value="{{ old('email') }}" required class="am-input" autocomplete="email" placeholder="you@email.com">
                </div>
            </div>
            <button type="submit" class="am-account-card__submit">
                <span>Send reset link</span>
            </button>
        </form>

        <p class="am-account-card__footer-link">
            <a href="{{ route('account.login') }}">Back to sign in</a>
        </p>
    </div>
</x-account-auth-layout>
@endsection
