@extends('layouts.store')

@section('title', 'Your Cart — Vyomika Atelier LLP')

@section('content')
@include('partials.am-page-hero', ['label' => 'Shopping', 'title' => 'Your Cart'])

<section class="am-page-body">
    <div class="am-container am-checkout-flow">
        @include('partials.am-breadcrumbs', ['items' => [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Shop', 'url' => route('shop.index')],
            ['label' => 'Cart'],
        ]])

        @include('partials.am-checkout-steps', ['current' => 1])

        @if($items->isEmpty())
            <div class="am-checkout-empty am-card">
                <div class="am-card__body">
                    <div class="am-checkout-empty__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6l-1-3H2"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                    </div>
                    <h2 class="am-checkout-empty__title">Your cart is empty</h2>
                    <p class="am-checkout-empty__text">Browse PVD partitions, metal furniture, and hardware to get started.</p>
                    <a href="{{ route('shop.index') }}" class="am-btn am-btn--primary">Shop Products</a>
                </div>
            </div>
        @else
            @php
                $shippingEst = $subtotal >= 5000 ? 0 : 199;
            @endphp
            <div class="am-checkout-layout">
                <div class="am-checkout-main">
                    <div class="am-cart-list am-card">
                        <div class="am-card__body am-cart-list__body">
                            @foreach($items as $item)
                            <article class="am-cart-row">
                                <a href="{{ route('shop.show', $item['product']->slug) }}" class="am-cart-thumb">
                                    @if($item['product']->imageUrl())
                                        <img src="{{ $item['product']->imageUrl() }}" alt="{{ $item['product']->name }}">
                                    @endif
                                </a>
                                <div class="am-cart-row__body">
                                    @if($item['product']->category)
                                    <p class="am-featured__cat">{{ $item['product']->category->name }}</p>
                                    @endif
                                    <h3 class="am-cart-row__name">
                                        <a href="{{ route('shop.show', $item['product']->slug) }}">{{ $item['product']->name }}</a>
                                    </h3>
                                    <p class="am-cart-row__unit">{{ $item['product']->formattedPrice() }} each</p>
                                    <form action="{{ route('cart.update', $item['product']) }}" method="POST" class="am-cart-row__qty-form">
                                        @csrf @method('PATCH')
                                        <label for="qty-{{ $item['product']->id }}">Quantity</label>
                                        <input type="number" id="qty-{{ $item['product']->id }}" name="quantity" value="{{ $item['quantity'] }}" min="1" max="{{ $item['product']->stock }}" class="am-qty-input">
                                        <button type="submit" class="am-btn am-btn--outline am-btn--sm">Update</button>
                                    </form>
                                </div>
                                <div class="am-cart-row__total">
                                    <p class="am-cart-row__line-total">₹{{ number_format($item['line_total'], 0) }}</p>
                                    <form action="{{ route('cart.remove', $item['product']) }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="am-cart-row__remove">Remove</button>
                                    </form>
                                </div>
                            </article>
                            @endforeach
                        </div>
                    </div>

                    @include('partials.am-pdp-checkout-trust')
                </div>

                <div class="am-checkout-sidebar">
                    @include('partials.am-order-summary', [
                        'items' => $items,
                        'subtotal' => $subtotal,
                        'shipping' => $shippingEst,
                        'showThumbs' => false,
                    ])
                    <div class="am-checkout-sidebar__actions">
                        <a href="{{ route('checkout.index') }}" class="am-btn am-btn--primary am-btn--full am-btn--lg">Proceed to Checkout</a>
                        <a href="{{ route('shop.index') }}" class="am-btn am-btn--outline am-btn--full">Continue Shopping</a>
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>
@endsection
