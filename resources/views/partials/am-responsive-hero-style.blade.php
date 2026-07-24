@php
    use App\Support\ResponsiveHero;

    $urls = ResponsiveHero::urls($hero ?? [], $fallbackDesktop ?? null);
    $hasImage = filled($urls['desktop']) || filled($urls['tablet']) || filled($urls['mobile']);
@endphp
@if($hasImage)
style="--hero-bg-desktop: url('{{ $urls['desktop'] }}'); --hero-bg-tablet: url('{{ $urls['tablet'] }}'); --hero-bg-mobile: url('{{ $urls['mobile'] }}');"
@endif
