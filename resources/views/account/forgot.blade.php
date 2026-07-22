@extends('layouts.store')

@section('title', 'Forgot Access — Vyomika Atelier')

@section('content')
<x-account-auth-layout>
    <div class="am-account-card">
        <h1 class="am-account-card__title">Forgot access</h1>
        <p class="am-account-card__lead">We will send a WhatsApp OTP to your registered mobile number.</p>

        @include('partials.am-account-alerts')

        @unless($providerReady)
        <p class="am-account-notice am-account-notice--warning" role="status">
            WhatsApp verification is not configured yet.
        </p>
        @endunless

        <form action="{{ route('account.forgot.send') }}" method="POST" class="am-account-card__form">
            @csrf
            <div class="am-account-card__field">
                <label>Mobile</label>
                @include('partials.am-account-phone-fields', ['countryCodes' => $countryCodes, 'fieldPrefix' => 'forgot'])
            </div>

            <x-form-protection-fields form-key="account_forgot_otp" :show-intent="false" />

            <button type="submit" class="am-account-card__submit" @disabled(! $providerReady)>
                <span>Send WhatsApp OTP</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
        </form>

        <p class="am-account-card__footer-link">
            <a href="{{ route('account.login') }}">Back to sign in</a>
        </p>
    </div>
</x-account-auth-layout>
@endsection
