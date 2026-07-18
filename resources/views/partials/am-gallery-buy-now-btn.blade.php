@props([
    'product',
    'class' => 'am-btn am-btn--card-primary',
])

<form action="{{ route('cart.add', $product) }}" method="POST" class="am-design-gallery__buy-form">
    @csrf
    <input type="hidden" name="quantity" value="1">
    <input type="hidden" name="buy_now" value="1">
    <button type="submit" class="{{ $class }}">Buy Now</button>
</form>
