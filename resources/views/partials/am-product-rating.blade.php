@php
    use App\Support\ProductReviewDisplay;

    $review = $product instanceof \App\Models\Product
        ? ProductReviewDisplay::summary($product)
        : null;
@endphp

@if($review)
<div class="am-product-card__rating" aria-label="Rated {{ $review['average'] }} out of 5 from {{ $review['count'] }} reviews">
    <span class="am-product-card__stars" aria-hidden="true">{{ str_repeat('★', (int) round($review['average'])) }}{{ str_repeat('☆', 5 - (int) round($review['average'])) }}</span>
    <span class="am-product-card__review-count">({{ number_format($review['count']) }})</span>
</div>
@endif
