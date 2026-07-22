@extends('layouts.store')

@section('title', 'Checkout — Vyomika Atelier')

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
        <p class="am-checkout-notice" role="alert">{{ config('addresses.payment_unavailable_message') }}</p>
        @endunless

        @php
            $addr = $defaultAddress ?? null;
            $addrMeta = $addr ? \App\Models\CustomerAddress::decodeLine2($addr->address_line2) : ['company' => '', 'country' => 'India'];
            $checkoutName = old('customer_name', $addr?->name ?? $user?->name ?? '');
            $checkoutParts = $checkoutName ? explode(' ', $checkoutName, 2) : ['', ''];
        @endphp

        <form action="{{ route('checkout.store') }}" method="POST" class="am-checkout-stack am-checkout-form am-address-form">
            @csrf
            <input type="hidden" name="payment_method" value="razorpay">

            <div class="am-card am-checkout-panel">
                <div class="am-card__body">
                    <h2 class="am-checkout-panel__title">Shipping details</h2>
                    <p class="am-checkout-panel__hint">Worldwide delivery · estimated 3–4 weeks after order confirmation</p>

                    <div class="am-checkout-form__address">
                        @include('partials.am-address-form-grid', [
                            'mode' => 'checkout',
                            'userEmail' => old('customer_email', $user?->email),
                            'firstName' => old('first_name', $checkoutParts[0] ?? ''),
                            'lastName' => old('last_name', $checkoutParts[1] ?? ''),
                            'company' => old('company', $addrMeta['company'] ?? ''),
                            'houseBuilding' => old('house_building', $addr?->house_building ?? $addr?->address_line1 ?? ''),
                            'street' => old('street', $addr?->street ?? ''),
                            'locality' => old('locality', $addr?->locality ?? ''),
                            'landmark' => old('landmark', $addr?->landmark ?? ''),
                            'city' => old('city', $addr?->city ?? $user?->city ?? ''),
                            'state' => old('state', $addr?->state ?? ''),
                            'pincode' => old('pincode', $addr?->pincode ?? ''),
                            'phone' => old('customer_phone', $addr?->phone ?? $user?->mobile ?? ''),
                            'altMobile' => old('alt_mobile', $addr?->alt_mobile ?? ''),
                            'country' => old('country', $addr?->country ?? $addrMeta['country'] ?? 'India'),
                            'addressType' => old('address_type', $addr?->address_type ?? 'home'),
                            'floor' => old('floor', $addr?->floor ?? ''),
                            'liftAvailable' => old('lift_available', $addr?->lift_available),
                            'deliveryInstructions' => old('delivery_instructions', $addr?->delivery_instructions ?? ''),
                        ])
                        <label class="am-account-consent am-address-form__default">
                            <input type="checkbox" name="save_address" value="1" @checked(old('save_address'))> Save this address to my account
                        </label>
                        <label class="am-account-consent am-address-form__default">
                            <input type="checkbox" name="billing_same_as_shipping" value="1" @checked(old('billing_same_as_shipping', true))> Billing address same as shipping
                        </label>
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
