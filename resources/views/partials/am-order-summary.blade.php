@props([
    'items' => [],
    'subtotal' => 0,
    'shipping' => 0,
    'total' => null,
    'title' => 'Order Summary',
    'showThumbs' => true,
    'compact' => false,
])

@php
    $grandTotal = $total ?? ($subtotal + $shipping);
@endphp

<aside class="am-order-summary {{ $compact ? 'am-order-summary--compact' : '' }}">
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
                            <span class="am-order-summary__qty">Qty {{ $qty }}</span>
                        </span>
                        <span class="am-order-summary__price">₹{{ number_format($lineTotal, 0) }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="am-order-summary__totals">
                <div class="am-order-summary__row">
                    <span>Subtotal</span>
                    <span>₹{{ number_format($subtotal, 0) }}</span>
                </div>
                <div class="am-order-summary__row am-order-summary__row--muted">
                    <span>Shipping</span>
                    <span>{{ $shipping > 0 ? '₹'.number_format($shipping, 0) : 'Free' }}</span>
                </div>
                <div class="am-order-summary__row am-order-summary__row--total">
                    <span>Total</span>
                    <span>₹{{ number_format($grandTotal, 0) }}</span>
                </div>
            </div>
        </div>
    </div>
</aside>
