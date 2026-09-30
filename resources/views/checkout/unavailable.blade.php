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

        @if(session('success'))
        <p class="am-checkout-notice" role="status">{{ session('success') }}</p>
        @endif
        @if(session('error'))
        <p class="am-checkout-notice am-checkout-notice--error" role="alert">{{ session('error') }}</p>
        @endif
        @include('partials.am-enquiry-receipt')

        <div class="am-checkout-success-card am-card">
            <div class="am-card__body">
                <h2 class="am-checkout-success-card__title">Checkout is temporarily unavailable</h2>
                <p class="am-checkout-success-card__text">{{ \App\Support\CheckoutPayments::UNAVAILABLE_MESSAGE }}</p>
                <p class="am-checkout-success-card__text">{{ \App\Support\IndiaDelivery::CUSTOMER_NOTE }}</p>
                <div class="am-checkout-success-card__actions">
                    <a href="{{ route('cart.index') }}" class="am-btn am-btn--primary">Return to cart</a>
                    <a href="{{ \App\Support\StorefrontRoutes::primaryShopUrl() }}" class="am-btn am-btn--outline">Continue shopping</a>
                </div>
            </div>
        </div>

        @if(isset($items) && $items->isNotEmpty())
        @php
            $addr = $defaultAddress ?? null;
            $addrMeta = $addr ? \App\Models\CustomerAddress::decodeLine2($addr->address_line2) : ['company' => '', 'country' => 'India'];
            $checkoutName = old('customer_name', $addr?->name ?? ($user->name ?? ''));
            $checkoutParts = $checkoutName ? explode(' ', $checkoutName, 2) : ['', ''];
            $selectedCountry = old('country', $addr?->country ?? $addrMeta['country'] ?? 'India');
            $destinationIsIndia = \App\Support\IndiaDelivery::isIndia($selectedCountry);
            $summaryMode = $destinationIsIndia ? 'india' : 'international';
        @endphp
        <form action="{{ route('checkout.store') }}" method="POST" class="am-checkout-stack am-checkout-form am-address-form">
            @csrf
            <x-form-protection-fields form-key="international_shipping" :show-intent="false" />
            <input type="hidden" name="enquiry_intent" value="general_enquiry">
            <div class="am-card am-checkout-panel">
                <div class="am-card__body">
                    <h2 class="am-checkout-panel__title" data-checkout-heading>{{ $destinationIsIndia ? 'Checkout unavailable' : 'International shipping enquiry' }}</h2>
                    <p class="am-checkout-panel__hint" data-destination-hint>{{ $destinationIsIndia ? \App\Support\CheckoutPayments::UNAVAILABLE_MESSAGE : \App\Support\IndiaDelivery::ENQUIRY_HINT }}</p>
                    <p class="am-checkout-panel__hint">{{ \App\Support\IndiaDelivery::READY_STOCK_ESTIMATE }} {{ \App\Support\IndiaDelivery::MADE_TO_ORDER_ESTIMATE }}</p>
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
            <button type="submit" class="am-btn am-btn--primary" data-checkout-submit data-pay-label="Checkout unavailable" data-enquiry-label="Save shipping enquiry">{{ $destinationIsIndia ? 'Checkout unavailable' : 'Save shipping enquiry' }}</button>
        </form>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var select = document.querySelector('[data-country-select]');
    var button = document.querySelector('[data-checkout-submit]');
    if (!select || !button) return;
    var heading = document.querySelector('[data-checkout-heading]');
    var hint = document.querySelector('[data-destination-hint]');
    var unavailable = @json(\App\Support\CheckoutPayments::UNAVAILABLE_MESSAGE);
    var enquiryHint = @json(\App\Support\IndiaDelivery::ENQUIRY_HINT);
    var sync = function () {
        var india = select.value === 'India';
        button.textContent = india ? button.getAttribute('data-pay-label') : button.getAttribute('data-enquiry-label');
        if (heading) heading.textContent = india ? 'Checkout unavailable' : 'International shipping enquiry';
        if (hint) hint.textContent = india ? unavailable : enquiryHint;
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
});
</script>
@endpush
