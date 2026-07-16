{{-- Page hero for inner pages --}}
@props(['label' => '', 'title' => '', 'subtitle' => null])
<section class="am-page-hero">
    <div class="am-container">
        @if($label)<p class="am-page-hero__label">{{ $label }}</p>@endif
        <h1 class="am-page-hero__title">{{ $title }}</h1>
        @if($subtitle)<p style="margin-top:0.75rem;opacity:0.75;font-size:0.95rem">{{ $subtitle }}</p>@endif
    </div>
</section>
