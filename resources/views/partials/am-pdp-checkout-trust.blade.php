@props(['product' => null, 'shippingContext' => null, 'shippingNote' => null])

@php
    $shippingNote = $shippingNote
    ?? \App\Support\IndiaDelivery::shippingNoteFor(
        $product instanceof \App\Models\Product ? $product : null,
        is_string($shippingContext) ? $shippingContext : null,
    );
    $detailLines = $product instanceof \App\Models\Product && filled($product->availability_mode) && $shippingContext === null
        ? \App\Support\ProductFulfilment::customerDetailLines($product)
        : [];
@endphp

<div class="am-pdp-checkout-trust">
    <ul class="am-pdp-shipping-notes">
        @foreach($detailLines as $detailLine)
        <li>
            <span>{{ $detailLine }}</span>
        </li>
        @endforeach
        <li>
            <svg class="am-pdp-shipping-notes__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            <span data-shipping-trust>{{ $shippingNote }}</span> <a href="{{ route('legal.shipping') }}">Shipping details</a>
        </li>
    </ul>

    <div class="am-pdp-safe-checkout">
        <p class="am-pdp-safe-checkout__title">Guaranteed Safe Checkout</p>
        <div class="am-payment-logos" role="list" aria-label="Accepted payment methods">
            <span class="am-pay-logo am-pay-logo--visa" role="listitem" title="Visa">
                <svg viewBox="0 0 48 16" aria-hidden="true"><text x="0" y="13" font-family="Arial Black, sans-serif" font-size="14" font-weight="700" fill="#1A1F71">VISA</text></svg>
            </span>
            <span class="am-pay-logo am-pay-logo--mastercard" role="listitem" title="Mastercard">
                <svg viewBox="0 0 32 20" aria-hidden="true"><circle cx="12" cy="10" r="8" fill="#EB001B"/><circle cx="20" cy="10" r="8" fill="#F79E1B" fill-opacity="0.9"/></svg>
            </span>
            <span class="am-pay-logo am-pay-logo--rupay" role="listitem" title="RuPay">
                <svg viewBox="0 0 56 16" aria-hidden="true"><text x="0" y="12" font-family="Arial, sans-serif" font-size="11" font-weight="700" fill="#097B44">RuPay</text></svg>
            </span>
            <span class="am-pay-logo am-pay-logo--razorpay" role="listitem" title="Razorpay">
                <svg viewBox="0 0 72 16" aria-hidden="true"><text x="0" y="12" font-family="Arial, sans-serif" font-size="10" font-weight="700" fill="#072654">Razorpay</text></svg>
            </span>
        </div>
    </div>
</div>
