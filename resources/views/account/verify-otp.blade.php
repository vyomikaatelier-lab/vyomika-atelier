@extends('layouts.store')

@section('title', 'Verify WhatsApp — Vyomika Atelier')

@section('content')
<x-account-auth-layout>
    <div class="am-account-card am-account-card--verify">
        <ol class="am-account-verify-sequence__steps am-account-verify-sequence__steps--compact" aria-label="WhatsApp verification steps">
            <li class="am-account-verify-sequence__step is-done">
                <span class="am-account-verify-sequence__num" aria-hidden="true">✓</span>
                <span>WhatsApp number</span>
            </li>
            <li class="am-account-verify-sequence__step is-active">
                <span class="am-account-verify-sequence__num">2</span>
                <span>Enter OTP</span>
            </li>
        </ol>

        <h1 class="am-account-card__title">Enter OTP</h1>
        <p class="am-account-verify__hint">Code sent to WhatsApp ending in <strong>{{ $maskedMobile }}</strong></p>

        @include('partials.am-account-alerts')

        <form action="{{ route('account.verify.submit') }}" method="POST" class="am-account-card__form" id="account-otp-form">
            @csrf
            <div class="am-account-card__field">
                <label>6-digit WhatsApp OTP</label>
                @include('partials.am-account-otp-inputs')
            </div>
            <input type="hidden" name="otp" id="otp-combined" value="">

            <x-form-protection-fields form-key="account_verify_otp" :show-intent="false" />

            <button type="submit" class="am-account-card__submit">
                <span>{{ config('account.copy.verify_otp') }}</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
        </form>

        <div class="am-account-verify__actions">
            @if($canResend && $providerReady)
            <form action="{{ route('account.resend') }}" method="POST">
                @csrf
                <x-form-protection-fields :form-key="$purpose === 'register' ? 'account_register' : ($purpose === 'forgot' ? 'account_forgot_otp' : 'account_login_otp')" :show-intent="false" />
                <button type="submit" class="am-account-card__link-btn">{{ config('account.copy.resend_otp') }}</button>
            </form>
            @else
            <p class="am-account-verify__countdown" id="otp-resend-countdown" data-seconds="{{ $resendSeconds }}">
                Resend available in <span>{{ $resendSeconds }}</span>s
            </p>
            @endif

            <a href="{{ route($purpose === 'register' ? 'account.register' : 'account.login') }}" class="am-account-verify__change">
                Change WhatsApp number
            </a>
        </div>
    </div>
</x-account-auth-layout>
@endsection

@push('scripts')
<script src="{{ asset('js/account-otp.js') }}" defer></script>
@endpush
