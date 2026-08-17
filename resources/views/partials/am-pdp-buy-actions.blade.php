@props(['product', 'externalSizeSelector' => false])

@php
    use App\Support\FinishSwatches;
    $defaultFinish = FinishSwatches::resolve(null);
    $defaultSize = $product->resolveSizeOption(null);
    $hasSizeOptions = $product->hasSizeOptions();
@endphp

<div class="am-pdp-buy">
    @if($product->inStock())
    <form action="{{ route('cart.add', $product) }}" method="POST" class="am-pdp-buy__form" data-pdp-buy-form>
        @csrf
        <input type="hidden" name="finish_slug" value="{{ $defaultFinish['slug'] }}" data-finish-input="slug">
        @if($defaultSize)
        <input type="hidden" name="size_label" value="{{ $defaultSize['label'] }}" data-size-input="label">
        <input type="hidden" name="unit_price" value="{{ $defaultSize['price'] }}" data-size-input="price">
        @endif
        @if($hasSizeOptions && ! $externalSizeSelector)
        <div class="am-pdp-buy__row am-pdp-buy__row--with-size">
            @include('partials.am-pdp-size-options', ['product' => $product, 'compact' => true])
            <div class="am-pdp-buy__qty">
                <label for="pdp-qty" class="am-pdp-buy__qty-label">Quantity</label>
                <input type="number" id="pdp-qty" name="quantity" value="1" min="1" max="{{ min($product->stock, 99) }}" class="am-input am-pdp-buy__qty-input" inputmode="numeric">
            </div>
        </div>
        @if($defaultSize)
        @include('partials.am-pdp-size-price', ['selectedSize' => $defaultSize])
        @endif
        @else
        <div class="am-pdp-buy__row">
            <div class="am-pdp-buy__qty">
                <label for="pdp-qty" class="am-pdp-buy__qty-label">Quantity</label>
                <input type="number" id="pdp-qty" name="quantity" value="1" min="1" max="{{ min($product->stock, 99) }}" class="am-input am-pdp-buy__qty-input" inputmode="numeric">
            </div>
        </div>
        @endif
        <div class="am-pdp-buy__actions">
            <button type="submit" class="am-btn am-btn--outline am-btn--lg am-pdp-buy__btn">Add to Bag</button>
            <button type="submit" name="buy_now" value="1" class="am-btn am-btn--primary am-btn--lg am-pdp-buy__btn">Buy Now</button>
        </div>
        <p class="am-pdp-buy__stock">{{ $product->stock }} in stock</p>
    </form>
    @else
    <p class="am-pdp-buy__oos">Out of stock — <a href="{{ route('contact.index') }}">contact the studio</a> for waitlist.</p>
    @endif
</div>
