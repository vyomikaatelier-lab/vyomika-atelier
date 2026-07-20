@extends('layouts.store')

@section('title', 'Checkout — Vyomika Atelier LLP')

@section('content')
@include('partials.am-page-hero', ['label' => 'Secure Checkout', 'title' => 'Checkout'])

<section class="am-page-body am-page-body--checkout">
    <div class="am-checkout-flow am-checkout-flow--centered">
        @include('partials.am-breadcrumbs', ['items' => [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Cart', 'url' => route('cart.index')],
            ['label' => 'Checkout'],
        ]])

        @include('partials.am-checkout-steps', ['current' => 2])

        @if(session('error'))
        <p class="am-checkout-notice am-checkout-notice--error" role="alert">{{ session('error') }}</p>
        @endif

        @if($errors->any())
        <div class="am-checkout-notice am-checkout-notice--error" role="alert">
            <p>Please fix the following:</p>
            <ul>
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @unless($razorpayEnabled)
        <p class="am-checkout-notice" role="alert">Online payment is not configured yet. Add <code>RAZORPAY_KEY_ID</code> and <code>RAZORPAY_KEY_SECRET</code> to the server <code>.env</code>, then run <code>php artisan config:cache</code>. Until then, use Contact Us to place an order.</p>
        @endunless

        <form action="{{ route('checkout.store') }}" method="POST" class="am-checkout-stack am-checkout-form am-address-form">
            @csrf
            <input type="hidden" name="payment_method" value="razorpay">

            <div class="am-card am-checkout-panel">
                <div class="am-card__body">
                    <h2 class="am-checkout-panel__title">Shipping details</h2>
                    <p class="am-checkout-panel__hint">Worldwide delivery · estimated 3–4 weeks after order confirmation</p>

                    @php
                        $checkoutName = old('customer_name', '');
                        $checkoutParts = $checkoutName ? explode(' ', $checkoutName, 2) : ['', ''];
                    @endphp
                    <div class="am-checkout-form__address">
                        @include('partials.am-address-form-grid', [
                            'mode' => 'checkout',
                            'userEmail' => old('customer_email'),
                            'firstName' => old('first_name', $checkoutParts[0] ?? ''),
                            'lastName' => old('last_name', $checkoutParts[1] ?? ''),
                            'company' => old('company'),
                            'street' => old('shipping_address'),
                            'city' => old('city'),
                            'state' => old('state'),
                            'pincode' => old('pincode'),
                            'phone' => old('customer_phone'),
                            'country' => old('country', 'India'),
                        ])
                        <div class="am-checkout-field am-address-form__field--full">
                            <label for="notes">Order notes <span class="am-checkout-field__optional">(optional)</span></label>
                            <textarea id="notes" name="notes" rows="2" class="am-input am-textarea" placeholder="Delivery instructions, GST details…">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="am-card am-checkout-panel am-checkout-panel--payment">
                <div class="am-card__body">
                    <h2 class="am-checkout-panel__title">Payment</h2>
                    <p class="am-checkout-panel__hint">Pay securely online with UPI or card after you place the order.</p>
                    <div class="am-checkout-pay-badges" aria-label="Accepted payment methods">
                        <span class="am-checkout-pay-badge">UPI</span>
                        <span class="am-checkout-pay-badge">Debit / Credit Card</span>
                        <span class="am-checkout-pay-badge">Net Banking</span>
                    </div>
                    @include('partials.am-pdp-checkout-trust')
                </div>
            </div>

            @include('partials.am-order-summary', [
                'items' => $items,
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'total' => $total,
                'compact' => true,
            ])

            <div class="am-checkout-stack__actions">
                <button type="submit" class="am-btn am-btn--primary am-btn--full am-btn--lg" @disabled(!$razorpayEnabled)>Continue to Payment</button>
                <a href="{{ route('cart.index') }}" class="am-btn am-btn--outline am-btn--full">Back to Cart</a>
            </div>
        </form>
    </div>
</section>
@endsection
