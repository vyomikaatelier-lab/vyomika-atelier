@extends('layouts.store')

@section('title', 'Checkout Unavailable — Vyomika Atelier')

@section('content')
@include('partials.am-page-hero', ['label' => 'Checkout', 'title' => 'Checkout is temporarily unavailable'])

<section class="am-page-body">
    <div class="am-container am-checkout-flow am-checkout-flow--centered">
        @include('partials.am-breadcrumbs', ['items' => [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Cart', 'url' => route('cart.index')],
            ['label' => 'Checkout unavailable'],
        ]])

        <div class="am-checkout-success-card am-card">
            <div class="am-card__body">
                <h2 class="am-checkout-success-card__title">Checkout is temporarily unavailable</h2>
                <p class="am-checkout-success-card__text">{{ \App\Support\CheckoutPayments::UNAVAILABLE_MESSAGE }}</p>
                <div class="am-checkout-success-card__actions">
                    <a href="{{ route('cart.index') }}" class="am-btn am-btn--primary">Return to cart</a>
                    <a href="{{ \App\Support\StorefrontRoutes::primaryShopUrl() }}" class="am-btn am-btn--outline">Continue shopping</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
