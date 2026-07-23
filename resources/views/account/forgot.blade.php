@extends('layouts.store')

@section('title', 'Forgot Access — Vyomika Atelier')

@section('content')
<x-account-auth-layout>
    <div class="am-account-card am-account-theme">
        <header class="am-account-card__header">
            <h1 class="am-account-card__hero-title">Forgot access</h1>
            <p class="am-account-card__subtitle">We will send a WhatsApp OTP to your registered mobile number.</p>
        </header>

        @include('partials.am-account-alerts')

        @unless($providerReady)
        <p class="am-account-notice am-account-notice--warning" role="status">
            WhatsApp verification is not configured yet.
        </p>
        @endunless

        <form action="{{ route('account.forgot.send') }}" method="POST" class="am-account-card__form">
            @csrf
            <div class="am-account-card__field">
                <label for="forgot-mobile">Mobile number (WhatsApp)</label>
                <div class="am-account-field-input am-account-field-input--phone">
                    @include('partials.am-account-field-icon', ['icon' => 'phone'])
                    @include('partials.am-account-phone-fields', ['countryCodes' => $countryCodes, 'fieldPrefix' => 'forgot'])
                </div>
                <p class="am-account-card__hint">WhatsApp verification code will be sent to this number</p>
            </div>

            <x-form-protection-fields form-key="account_forgot_otp" :show-intent="false" />

            <button type="submit" class="am-account-card__submit" @disabled(! $providerReady)>
                <span>Send WhatsApp OTP</span>
            </button>
        </form>

        <p class="am-account-card__footer-link">
            <a href="{{ route('account.login') }}">Back to sign in</a>
        </p>
    </div>
</x-account-auth-layout>
@endsection
