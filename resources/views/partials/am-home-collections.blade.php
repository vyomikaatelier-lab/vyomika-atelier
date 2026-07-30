@php
    use App\Support\HomeSections;
    use App\Support\SiteContent;
    use App\Support\StorefrontUrl;

    $collectionRow = SiteContent::get('collection_row', []);
    $collectionCards = HomeSections::collectionCards();
@endphp

@if(!empty($collectionCards))
<section class="am-section am-section--white am-section--edge am-collections" id="shop-by-collection" aria-labelledby="am-collections-title">
    <div class="am-section__intro">
        <div class="am-section-head am-section-head--row">
            <div>
                <h2 id="am-collections-title">{{ $collectionRow['title'] ?? 'Shop by Collection' }}</h2>
                <p>{{ $collectionRow['subtitle'] ?? '' }}</p>
            </div>
            @if(filled($collectionRow['cta_label'] ?? null))
            <a href="{{ StorefrontUrl::to('shop.index', [], '/shop') }}" class="am-section-head__link">{{ $collectionRow['cta_label'] }}</a>
            @endif
        </div>
    </div>
    <div class="am-section__body">
        <div class="am-collection-row">
            @foreach($collectionCards as $card)
            <a href="{{ $card['url'] }}" class="am-collection-card">
                <span class="am-collection-card__media">
                    @if($card['image'])
                    <img src="{{ $card['image'] }}" alt="{{ $card['label'] }}" loading="lazy">
                    @endif
                </span>
                <span class="am-collection-card__name">{{ $card['label'] }}</span>
                @if(filled($card['caption']))
                <span class="am-collection-card__caption">{{ $card['caption'] }}</span>
                @endif
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif
