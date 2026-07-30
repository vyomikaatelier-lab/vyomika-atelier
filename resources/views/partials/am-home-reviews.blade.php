@php
    use App\Support\HomeSections;

    $reviews = HomeSections::reviews();
@endphp

@if(!empty($reviews['cards']))
<section class="am-section am-section--white am-section--edge am-reviews" id="reviews" aria-labelledby="am-reviews-title">
    <div class="am-section__intro">
        <div class="am-section-head">
            <h2 id="am-reviews-title">{{ $reviews['title'] }}</h2>
            @if(filled($reviews['subtitle']))
            <p>{{ $reviews['subtitle'] }}</p>
            @endif
            @if(filled($reviews['rating_label']))
            <p class="am-reviews__rating">
                <span class="am-review-card__stars" aria-hidden="true">★★★★★</span>
                <span>{{ $reviews['rating_label'] }}</span>
            </p>
            @endif
        </div>
    </div>
    <div class="am-section__body">
        <div class="am-review-grid">
            @foreach($reviews['cards'] as $review)
            <figure class="am-review-card">
                <span class="am-review-card__stars" aria-label="{{ $review['rating'] }} out of 5 stars">{{ str_repeat('★', $review['rating']) }}{{ str_repeat('☆', 5 - $review['rating']) }}</span>
                <blockquote class="am-review-card__quote">“{{ $review['quote'] }}”</blockquote>
                <figcaption class="am-review-card__author">
                    <span class="am-review-card__name">{{ $review['client'] }}</span>
                    <span class="am-review-card__meta">
                        @if($review['verified'])
                        <span class="am-review-card__verified">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>
                            Verified buyer
                        </span>
                        @endif
                        @if(filled($review['location']))
                        <span class="am-review-card__location">{{ $review['location'] }}</span>
                        @endif
                    </span>
                </figcaption>
            </figure>
            @endforeach
        </div>
    </div>
</section>
@endif
