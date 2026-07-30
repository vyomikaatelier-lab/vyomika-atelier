@php
    use App\Support\HomeSections;

    $quoteCta = HomeSections::quoteCta();
@endphp

@if(!empty($quoteCta))
<section class="am-section am-section--edge am-quote-cta-section" id="get-a-quote" aria-labelledby="am-quote-cta-title">
    <div class="am-section__body">
        <div class="am-quote-cta">
            <div class="am-quote-cta__media">
                @if(filled($quoteCta['image'] ?? null))
                <img src="{{ $quoteCta['image'] }}" alt="{{ $quoteCta['image_alt'] ?? $quoteCta['title'] }}" loading="lazy">
                @endif
            </div>
            <div class="am-quote-cta__body">
                @if(filled($quoteCta['kicker'] ?? null))
                <p class="am-quote-cta__kicker">{{ $quoteCta['kicker'] }}</p>
                @endif
                <h2 class="am-quote-cta__title" id="am-quote-cta-title">{{ $quoteCta['title'] }}</h2>
                @if(filled($quoteCta['description'] ?? null))
                <p class="am-quote-cta__text">{{ $quoteCta['description'] }}</p>
                @endif
                @if(!empty($quoteCta['points']))
                <ul class="am-quote-cta__points">
                    @foreach($quoteCta['points'] as $point)
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>
                        <span>{{ $point }}</span>
                    </li>
                    @endforeach
                </ul>
                @endif
                <div class="am-quote-cta__actions">
                    <a href="{{ url($quoteCta['primary_cta_href'] ?? '/custom-order') }}" class="am-btn am-btn--primary am-btn--lg">{{ $quoteCta['primary_cta_label'] ?? 'Request a Quote' }}</a>
                    @if(filled($quoteCta['secondary_cta_label'] ?? null))
                    <a href="{{ url($quoteCta['secondary_cta_href'] ?? '/contact') }}" class="am-btn am-btn--outline am-btn--lg">{{ $quoteCta['secondary_cta_label'] }}</a>
                    @endif
                </div>
                @if(filled($quoteCta['footnote'] ?? null))
                <p class="am-quote-cta__footnote">{{ $quoteCta['footnote'] }}</p>
                @endif
            </div>
        </div>
    </div>
</section>
@endif
