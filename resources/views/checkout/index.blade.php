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

        @if(session('success'))
        <p class="am-checkout-notice" role="status">{{ session('success') }}</p>
        @endif
        @if(session('info'))
        <p class="am-checkout-notice" role="status">{{ session('info') }}</p>
        @endif
        @include('partials.am-enquiry-receipt')

        @if(session('error'))
        <p class="am-checkout-notice am-checkout-notice--error" role="alert">{{ session('error') }}</p>
        @endif

        @if(session('resume_payment_url'))
        <p class="am-checkout-notice" role="status">
            @if(session('resume_order_number'))
            Order #{{ session('resume_order_number') }} still has an active payment.
            @endif
            <a href="{{ session('resume_payment_url') }}" class="am-btn am-btn--primary">Resume Payment</a>
        </p>
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
            $selectedCountry = old('country', $addr?->country ?? $addrMeta['country'] ?? 'India');
            $destinationIsIndia = \App\Support\IndiaDelivery::isIndia($selectedCountry);
            $summaryMode = $destinationIsIndia ? 'india' : 'international';
        @endphp

        <form action="{{ route('checkout.store') }}" method="POST" class="am-checkout-stack am-checkout-form am-address-form">
            @csrf
            <input type="hidden" name="payment_method" value="razorpay">
            <x-form-protection-fields form-key="international_shipping" :show-intent="false" />
            <input type="hidden" name="enquiry_intent" value="general_enquiry">

            <div class="am-card am-checkout-panel">
                <div class="am-card__body">
                    <h2 class="am-checkout-panel__title">Shipping details</h2>
                    <p class="am-checkout-panel__hint" data-destination-hint>{{ $destinationIsIndia ? \App\Support\IndiaDelivery::CUSTOMER_NOTE : \App\Support\IndiaDelivery::ENQUIRY_HINT }}</p>
                    <p class="am-checkout-panel__hint">{{ \App\Support\IndiaDelivery::READY_STOCK_ESTIMATE }} {{ \App\Support\IndiaDelivery::MADE_TO_ORDER_ESTIMATE }}</p>

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
                    <p class="am-checkout-panel__hint" data-payment-hint>{{ $destinationIsIndia ? 'India orders are paid with Razorpay (UPI, card, or net banking).' : 'International delivery is saved as an enquiry. Our team confirms shipping before payment.' }}</p>
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
                'summaryMode' => $summaryMode,
            ])

            <div class="am-checkout-stack__actions">
                <button type="submit" class="am-btn am-btn--primary am-btn--full am-btn--lg" data-checkout-submit data-pay-label="Continue to Payment" data-enquiry-label="Save shipping enquiry" data-razorpay-ready="{{ $razorpayEnabled ? '1' : '0' }}" @disabled($destinationIsIndia && !$razorpayEnabled)>{{ $destinationIsIndia ? 'Continue to Payment' : 'Save shipping enquiry' }}</button>
                <a href="{{ route('cart.index') }}" class="am-btn am-btn--outline am-btn--full">Back to Cart</a>
            </div>
        </form>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var select = document.querySelector('[data-country-select]');
    var button = document.querySelector('[data-checkout-submit]');
    if (!select || !button) return;
    var payLabel = button.getAttribute('data-pay-label');
    var enquiryLabel = button.getAttribute('data-enquiry-label');
    var razorpayReady = button.getAttribute('data-razorpay-ready') === '1';
    var hint = document.querySelector('[data-destination-hint]');
    var paymentHint = document.querySelector('[data-payment-hint]');
    var indiaHint = @json(\App\Support\IndiaDelivery::CUSTOMER_NOTE);
    var enquiryHint = @json(\App\Support\IndiaDelivery::ENQUIRY_HINT);
    var sync = function () {
        var india = select.value === 'India';
        button.textContent = india ? payLabel : enquiryLabel;
        button.disabled = india && !razorpayReady;
        if (hint) hint.textContent = india ? indiaHint : enquiryHint;
        if (paymentHint) {
            paymentHint.textContent = india
                ? 'India orders are paid with Razorpay (UPI, card, or net banking).'
                : 'International delivery is saved as an enquiry. Our team confirms shipping before payment.';
        }
        document.querySelectorAll('[data-order-summary]').forEach(function (summary) {
            var subtotal = summary.querySelector('[data-subtotal-label]');
            var shipping = summary.querySelector('[data-shipping-label]');
            var payable = summary.querySelector('[data-payable-total]');
            if (subtotal) subtotal.textContent = india ? 'Subtotal' : @json(\App\Support\IndiaDelivery::MERCHANDISE_SUBTOTAL);
            if (shipping) shipping.textContent = india ? 'Shipping included' : @json(\App\Support\IndiaDelivery::SHIPPING_QUOTED);
            if (payable) payable.hidden = !india;
        });
    };
    select.addEventListener('change', sync);
    sync();
});
</script>
@endpush
