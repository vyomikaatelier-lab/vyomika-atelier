@extends('layouts.store')

@section('title', 'Reset Password — Vyomika Atelier')

@section('content')
<x-account-auth-layout>
    @php
        $forgotPending = $forgotPending ?? null;
        $forgotOtpVerified = $forgotOtpVerified ?? false;
        $forgotLocked = ! empty($forgotPending);
    @endphp
    <div class="am-account-card am-account-theme">
        <header class="am-account-card__header">
            <h1 class="am-account-card__hero-title">Reset password</h1>
            <p class="am-account-card__subtitle">Verify your WhatsApp number, then set a new password.</p>
        </header>

        @include('partials.am-account-alerts')

        @unless($providerReady)
        <p class="am-account-notice am-account-notice--warning" role="status">
            WhatsApp verification is not configured yet.
        </p>
        @endunless

        @if($forgotLocked)
        <div class="am-account-card__field">
            <label for="forgot-mobile-locked">Mobile number (WhatsApp)</label>
            <div class="am-account-field-input am-account-field-input--readonly">
                @include('partials.am-account-field-icon', ['icon' => 'phone'])
                <input type="text" id="forgot-mobile-locked" value="{{ $forgotMaskedMobile }}" class="am-input" readonly>
            </div>
        </div>
        @else
        <form action="{{ route('account.forgot.send') }}" method="POST" class="am-account-card__form" id="account-forgot-send-form">
            @csrf
            <div class="am-account-card__field">
                <label for="forgot-mobile">Mobile number (WhatsApp)</label>
                <div class="am-account-field-input am-account-field-input--phone">
                    @include('partials.am-account-field-icon', ['icon' => 'phone'])
                    @include('partials.am-account-phone-fields', ['countryCodes' => $countryCodes, 'fieldPrefix' => 'forgot'])
                </div>
                <div class="am-account-field__action-row">
                    <button type="submit" class="am-account-send-otp" @disabled(! $providerReady)>Send OTP</button>
                </div>
            </div>
            <x-form-protection-fields form-key="account_forgot_otp" :show-intent="false" />
        </form>
        @endif

        <div class="am-account-signup__otp {{ $forgotLocked && ! $forgotOtpVerified ? 'is-active' : '' }} {{ $forgotOtpVerified ? 'is-done' : '' }}">
            <div class="am-account-card__field">
                <label for="otp-combined">OTP</label>
                @if($forgotLocked && ! $forgotOtpVerified)
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
                    @if($forgotCanResend && $providerReady)
                    <form action="{{ route('account.resend') }}" method="POST">
                        @csrf
                        <x-form-protection-fields form-key="account_forgot_otp" :show-intent="false" />
                        <button type="submit" class="am-account-card__link-btn">{{ config('account.copy.resend_otp') }}</button>
                    </form>
                    @elseif($forgotLocked)
                    <p class="am-account-verify__countdown" id="otp-resend-countdown" data-seconds="{{ $forgotResendSeconds }}">
                        Resend available in <span>{{ $forgotResendSeconds }}</span>s
                    </p>
                    @endif
                    <a href="{{ route('account.forgot', ['change_number' => 1]) }}" class="am-account-verify__change">
                        Change number
                    </a>
                </div>
                @elseif($forgotOtpVerified)
                <p class="am-account-signup__status am-account-signup__status--inline" role="status">WhatsApp number verified.</p>
                @else
                @include('partials.am-account-otp-inputs', ['disabled' => true])
                <p class="am-account-card__hint">Send OTP to your registered mobile number.</p>
                @endif
            </div>
        </div>

        @if($forgotLocked && $forgotOtpVerified)
        <section class="am-account-signup__complete">
            <form action="{{ route('account.forgot.reset') }}" method="POST" class="am-account-card__form am-account-signup__complete-form">
                @csrf
                <div class="am-account-card__field">
                    <label for="forgot-password">New password</label>
                    <div class="am-account-field-input">
                        @include('partials.am-account-field-icon', ['icon' => 'password'])
                        <input type="password" name="password" id="forgot-password" required class="am-input" autocomplete="new-password" minlength="8" placeholder="New password">
                    </div>
                </div>
                <div class="am-account-card__field">
                    <label for="forgot-password-confirmation">Confirm password</label>
                    <div class="am-account-field-input">
                        @include('partials.am-account-field-icon', ['icon' => 'password'])
                        <input type="password" name="password_confirmation" id="forgot-password-confirmation" required class="am-input" autocomplete="new-password" minlength="8" placeholder="Confirm password">
                    </div>
                </div>
                <x-form-protection-fields form-key="account_forgot_otp" :show-intent="false" />
                <button type="submit" class="am-account-card__submit">
                    <span>Update password</span>
                </button>
            </form>
        </section>
        @endif

        <p class="am-account-card__footer-link">
            <a href="{{ route('account.login') }}">Back to sign in</a>
        </p>
    </div>
</x-account-auth-layout>
@endsection

@push('scripts')
@if(! empty($forgotPending) && empty($forgotOtpVerified))
<script src="{{ asset('js/account-otp.js') }}" defer></script>
@endif
@endpush
