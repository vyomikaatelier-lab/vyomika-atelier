@extends('layouts.store')

@section('title', 'Payment Session Expired — Vyomika Atelier')

@section('content')
@include('partials.am-page-hero', ['label' => 'Payment', 'title' => 'Payment session expired'])

<section class="am-page-body">
    <div class="am-container am-checkout-flow am-checkout-flow--centered">
        @include('partials.am-breadcrumbs', ['items' => [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Shop', 'url' => \App\Support\StorefrontRoutes::primaryShopUrl()],
            ['label' => 'Payment expired'],
        ]])

        <div class="am-checkout-success-card am-card">
            <div class="am-card__body">
                <h2 class="am-checkout-success-card__title">Payment session expired</h2>
                <p class="am-checkout-success-card__text">This payment session has expired. Order #{{ $order->order_number }} was not confirmed.</p>
                <div class="am-checkout-notice">
                    <p>Your cart is unchanged. You can place a new order when you are ready.</p>
                </div>
                <div class="am-checkout-success-card__actions">
                    <a href="{{ route('cart.index') }}" class="am-btn am-btn--primary">Return to cart</a>
                    <a href="{{ \App\Support\StorefrontRoutes::primaryShopUrl() }}" class="am-btn am-btn--outline">Continue shopping</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
