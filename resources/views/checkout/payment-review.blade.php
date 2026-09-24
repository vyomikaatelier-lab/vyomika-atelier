@extends('layouts.store')

@section('title', 'Payment Received — Vyomika Atelier')

@section('content')
@include('partials.am-page-hero', ['label' => 'Payment', 'title' => 'Payment received — order under review'])

<section class="am-page-body">
    <div class="am-container am-checkout-flow am-checkout-flow--centered">
        @include('partials.am-breadcrumbs', ['items' => [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Shop', 'url' => \App\Support\StorefrontRoutes::primaryShopUrl()],
            ['label' => 'Payment under review'],
        ]])

        <div class="am-checkout-success-card am-card">
            <div class="am-card__body">
                <h2 class="am-checkout-success-card__title">Payment received — order under review</h2>
                <p class="am-checkout-success-card__text">We received your payment for order #{{ $order->order_number }}. The studio is reviewing it.</p>
                <div class="am-checkout-notice" role="status">
                    <p>You do not need to pay again. We will contact you if we need anything else.</p>
                </div>
                <div class="am-checkout-success-card__actions">
                    <a href="{{ route('account') }}" class="am-btn am-btn--primary">View your account</a>
                    <a href="{{ \App\Support\StorefrontRoutes::primaryShopUrl() }}" class="am-btn am-btn--outline">Continue shopping</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
