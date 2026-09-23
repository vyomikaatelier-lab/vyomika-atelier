@props([
    'exploreHref' => '#',
    'exploreLabel' => 'Learn more',
    'primaryLabel' => 'Submit',
    'primaryType' => 'submit',
    'formId' => null,
    'orderServiceSlug' => '',
    'orderDesignSlug' => '',
    'orderServiceName' => '',
])

<div class="am-studio-spotlight__actions">
    @if($primaryType === 'order')
    <button
        type="button"
        class="am-btn am-btn--primary am-btn--sm va-order-btn"
        data-service-slug="{{ $orderServiceSlug }}"
        data-design-slug="{{ $orderDesignSlug }}"
        data-service-name="{{ $orderServiceName }}"
    >{{ $primaryLabel }}</button>
    @else
    <button type="submit" form="{{ $formId }}" class="am-btn am-btn--primary am-btn--sm">{{ $primaryLabel }}</button>
    @endif
    <a href="{{ url($exploreHref) }}" class="am-btn am-btn--outline am-btn--sm">{{ $exploreLabel }}</a>
</div>
