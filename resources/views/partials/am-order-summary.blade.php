@props([
    'items' => [],
    'subtotal' => 0,
    'shipping' => 0,
    'total' => null,
    'title' => 'Order Summary',
    'showThumbs' => true,
    'compact' => false,
    'summaryMode' => 'unselected',
])

@php
    $grandTotal = $total ?? ($subtotal + $shipping);
    $summaryMode = in_array($summaryMode, ['india', 'international', 'unselected'], true) ? $summaryMode : 'unselected';
    $subtotalLabel = $summaryMode === 'international'
        ? \App\Support\IndiaDelivery::MERCHANDISE_SUBTOTAL
        : 'Subtotal';
    $shippingLabel = match ($summaryMode) {
        'international' => \App\Support\IndiaDelivery::SHIPPING_QUOTED,
        'india' => 'Shipping included',
        default => \App\Support\IndiaDelivery::SHIPPING_INCLUDED_INDIA,
    };
@endphp

<aside class="am-order-summary {{ $compact ? 'am-order-summary--compact' : '' }}" data-order-summary data-summary-mode="{{ $summaryMode }}">
    <div class="am-order-summary__card am-card">
        <div class="am-card__body">
            <h2 class="am-order-summary__title">{{ $title }}</h2>

            <ul class="am-order-summary__lines">
                @foreach($items as $item)
                    @php
                        $product = $item['product'] ?? null;
                        $name = $product?->name ?? ($item['product_name'] ?? 'Item');
                        $qty = $item['quantity'] ?? 1;
                        $lineTotal = $item['line_total'] ?? ($item['total'] ?? 0);
                    @endphp
                    <li class="am-order-summary__line {{ (!$showThumbs || !$product?->imageUrl()) ? 'am-order-summary__line--plain' : '' }}">
                        @if($showThumbs && $product?->imageUrl())
                            <span class="am-order-summary__thumb">
                                <img src="{{ $product->imageUrl() }}" alt="">
                            </span>
                        @endif
                        <span class="am-order-summary__meta">
                            <span class="am-order-summary__name">{{ $name }}</span>
                            @if(!empty($item['size_label']) || !empty($item['finish_name']))
                            <span class="am-order-summary__variant">{{ trim(implode(' · ', array_filter([$item['size_label'] ?? null, $item['finish_name'] ?? null]))) }}</span>
                            @endif
                            <span class="am-order-summary__qty">
                                Qty {{ $qty }}
                                @if(!empty($item['unit_price']))
                                · {{ \App\Support\StorefrontPrice::formatInr($item['unit_price']) }} each
                                @endif
                            </span>
                        </span>
                        <span class="am-order-summary__price">₹{{ number_format($lineTotal, 0) }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="am-order-summary__totals">
                <div class="am-order-summary__row">
                    <span data-subtotal-label>{{ $subtotalLabel }}</span>
                    <span>₹{{ number_format($subtotal, 0) }}</span>
                </div>
                <div class="am-order-summary__row am-order-summary__row--muted">
                    <span>Shipping</span>
                    <span data-shipping-label>{{ $summaryMode === 'unselected' || $shipping <= 0 ? $shippingLabel : '₹'.number_format($shipping, 0) }}</span>
                </div>
                <div class="am-order-summary__row am-order-summary__row--total" data-payable-total @if($summaryMode === 'international') hidden @endif>
                    <span>Total</span>
                    <span>₹{{ number_format($grandTotal, 0) }}</span>
                </div>
                <p class="am-order-summary__tax">Prices include GST where applicable. {{ $summaryMode === 'international' ? \App\Support\IndiaDelivery::ENQUIRY_HINT : \App\Support\IndiaDelivery::CUSTOMER_NOTE }}</p>
            </div>
        </div>
    </div>
</aside>
