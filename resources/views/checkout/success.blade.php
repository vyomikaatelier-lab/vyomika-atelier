@extends('layouts.store')

@section('title', 'Order Confirmed — Vyomika Atelier LLP')

@section('content')
@include('partials.am-page-hero', ['label' => 'Thank You', 'title' => 'Order Confirmed'])

<section class="am-page-body">
    <div class="am-container am-checkout-flow am-checkout-flow--centered">
        @include('partials.am-breadcrumbs', ['items' => [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Shop', 'url' => route('shop.index')],
            ['label' => 'Order confirmed'],
        ]])

        @include('partials.am-checkout-steps', ['current' => 4])

        <div class="am-checkout-success-card am-card">
            <div class="am-card__body">
                <div class="am-checkout-success-card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg>
                </div>
                <h2 class="am-checkout-success-card__title">Thank you for your order</h2>
                <p class="am-checkout-success-card__text">Your order has been placed successfully.</p>
                <p class="am-checkout-success-card__order">Order #{{ $order->order_number }}</p>
                @if($orderEmailSent)
                <p class="am-checkout-success-card__email">Confirmation email sent to <strong>{{ $order->customer_email }}</strong></p>
                @else
                <p class="am-checkout-success-card__email">Order details for <strong>{{ $order->customer_email }}</strong>. If you do not receive an email shortly, contact us at <a href="mailto:{{ config('site.brand.email') }}">{{ config('site.brand.email') }}</a>.</p>
                @endif

                @if($order->status === 'paid')
                <div class="am-checkout-notice am-checkout-notice--success">
                    <p>Payment received. We will begin processing your order shortly.@if($paymentEmailSent) A payment confirmation email has been sent.@endif</p>
                </div>
                @elseif($order->payment_method === 'razorpay')
                <div class="am-checkout-notice">
                    <p>Your order is awaiting payment. Please complete checkout to confirm your order.</p>
                </div>
                @else
                <div class="am-checkout-notice am-checkout-notice--success">
                    <p>Your order is confirmed. Thank you for shopping with Vyomika Atelier.</p>
                </div>
                @endif

                @if($order->items->isNotEmpty())
                <div class="am-checkout-summary-block">
                    <h3 class="am-checkout-summary-block__title">Items ordered</h3>
                    <ul class="am-order-summary__lines">
                        @foreach($order->items as $item)
                        <li class="am-order-summary__line">
                            <span class="am-order-summary__meta">
                                <span class="am-order-summary__name">{{ $item->product_name }}</span>
                                <span class="am-order-summary__qty">Qty {{ $item->quantity }}</span>
                            </span>
                            <span class="am-order-summary__price">₹{{ number_format($item->total, 0) }}</span>
                        </li>
                        @endforeach
                    </ul>
                    <div class="am-order-summary__totals">
                        <div class="am-order-summary__row am-order-summary__row--total">
                            <span>Total</span>
                            <span>₹{{ number_format($order->total, 0) }}</span>
                        </div>
                    </div>
                </div>
                @endif

                <div class="am-checkout-success-card__actions">
                    <a href="{{ route('shop.index') }}" class="am-btn am-btn--primary">Continue Shopping</a>
                    <button type="button" class="am-btn am-btn--outline" data-open-contact-studio data-contact-context="Order #{{ $order->order_number }}">Contact Us</button>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
