@php
    use App\Support\Seo\JsonLd;
    use App\Support\Seo\PageSeo;

    $pageSeo = isset($pageSeo) && is_array($pageSeo) ? $pageSeo : [];
    $seo = PageSeo::make($pageSeo);
    $analytics = PageSeo::analytics();
@endphp
<meta name="description" content="{{ $seo['description'] }}">
@if(!empty($seo['robots']))
<meta name="robots" content="{{ $seo['robots'] }}">
@endif
<link rel="canonical" href="{{ $seo['canonical'] }}">
<meta property="og:title" content="{{ $seo['og_title'] }}">
<meta property="og:description" content="{{ $seo['og_description'] }}">
<meta property="og:type" content="{{ $seo['og_type'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
@if(!empty($seo['og_image']))
<meta property="og:image" content="{{ $seo['og_image'] }}">
@endif
<meta name="twitter:card" content="{{ !empty($seo['og_image']) ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seo['og_title'] }}">
<meta name="twitter:description" content="{{ $seo['og_description'] }}">
@if(!empty($seo['og_image']))
<meta name="twitter:image" content="{{ $seo['og_image'] }}">
@endif
@if(!empty($analytics['gsc']))
<meta name="google-site-verification" content="{{ $analytics['gsc'] }}">
@endif
{!! JsonLd::script(JsonLd::organization()) !!}
{!! JsonLd::script(JsonLd::website()) !!}
@stack('jsonld')
@if(!empty($analytics['ga4']))
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $analytics['ga4'] }}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', @json($analytics['ga4']));
</script>
@endif
