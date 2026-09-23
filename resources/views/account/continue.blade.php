@extends('layouts.store')

@section('title', 'Continue to checkout — Vyomika Atelier')

@section('content')
<section class="am-page-body am-account-continue">
    <div class="am-container" style="max-width:32rem;margin:3rem auto">
        <div class="am-account-card am-account-theme">
            <header class="am-account-card__header">
                <h1 class="am-account-card__hero-title">Continue to checkout</h1>
                <p class="am-account-card__subtitle">Sign in or create an account to continue securely to checkout.</p>
            </header>

            @include('partials.am-account-alerts')

            <div class="am-account-card__panel" style="display:grid;gap:0.75rem">
                <a href="{{ route('account.login') }}" class="am-btn am-btn--primary am-btn--full">Login</a>
                <a href="{{ route('account.register') }}" class="am-btn am-btn--outline am-btn--full">Create Account</a>
            </div>
        </div>
    </div>
</section>
@endsection
