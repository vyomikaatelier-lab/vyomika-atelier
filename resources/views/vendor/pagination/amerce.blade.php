@if ($paginator->hasPages())
<nav class="am-pagination" role="navigation" aria-label="Pagination">
    @if ($paginator->onFirstPage())
        <span class="am-pagination__btn is-disabled" aria-disabled="true">← Prev</span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" class="am-pagination__btn" rel="prev">← Prev</a>
    @endif

    @foreach ($elements as $element)
        @if (is_string($element))
            <span class="am-pagination__gap">{{ $element }}</span>
        @endif
        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span class="am-pagination__btn is-active" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="am-pagination__btn">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" class="am-pagination__btn" rel="next">Next →</a>
    @else
        <span class="am-pagination__btn is-disabled" aria-disabled="true">Next →</span>
    @endif
</nav>
@endif
