@extends('layouts.store')

@section('title', 'Refund completed — Vyomika Atelier')

@section('content')
@include('partials.am-page-hero', ['label' => 'Order', 'title' => 'Refund completed'])

<section class="am-page-body">
    <div class="am-container am-checkout-flow am-checkout-flow--centered">
        @include('partials.am-breadcrumbs', ['items' => [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Shop', 'url' => \App\Support\StorefrontRoutes::primaryShopUrl()],
            ['label' => 'Refund completed'],
        ]])

        <div class="am-checkout-success-card am-card">
            <div class="am-card__body">
                <h2 class="am-checkout-success-card__title">Refund completed</h2>
                <p class="am-checkout-success-card__text">{{ $order->customerRefundSummary() }}</p>
                <p class="am-checkout-success-card__order">Order #{{ $order->order_number }}</p>
                <div class="am-checkout-notice">
                    <p>Banks and the payment gateway can take several business days to show the refund.</p>
                </div>
                <div class="am-checkout-success-card__actions">
                    <a href="{{ \App\Support\StorefrontRoutes::primaryShopUrl() }}" class="am-btn am-btn--primary">Continue shopping</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
