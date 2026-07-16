@props([
    'name' => '',
    'slug' => '',
    'serviceSlug' => '',
    'category' => '',
    'finish' => '',
    'price' => null,
    'class' => 'am-btn am-btn--card-primary',
])

<button type="button"
    class="{{ $class }}"
    data-open-order-popup
    data-open-contact-studio
    data-popup-type="order_now"
    data-product-name="{{ $name }}"
    data-product-slug="{{ $slug }}"
    data-service-slug="{{ $serviceSlug }}"
    @if($category) data-category="{{ $category }}" @endif
    @if($finish) data-finish="{{ $finish }}" @endif
    @if($price !== null && $price !== '') data-price="{{ $price }}" @endif>
    Order Now
</button>
