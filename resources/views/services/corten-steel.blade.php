@extends('layouts.store')

@php
    $page = \App\Support\CortenContent::all();
    $hero = $page['hero'] ?? [];
@endphp

@section('title', $service->meta_title ?? \App\Support\CortenContent::metaTitle())

@push('meta')
<meta name="description" content="{{ $service->meta_description ?? \App\Support\CortenContent::metaDescription() }}">
@endpush

@section('content')

{{-- 1. Hero --}}
<section class="am-corten-hero" style="--corten-hero-img: url('{{ $hero['image'] ?? $service->image }}')">
    <div class="am-corten-hero__overlay"></div>
    <div class="am-container am-corten-hero__inner">
        <p class="am-page-hero__label">Corten Steel</p>
        <h1 class="am-corten-hero__title">{{ $hero['title'] ?? $service->name }}</h1>
        <p class="am-corten-hero__subtitle">{{ $hero['subtitle'] ?? $service->summary }}</p>
        <div class="am-corten-hero__actions">
            @if(!empty($hero['cta_primary']['href']))
            <a href="{{ $hero['cta_primary']['href'] }}" class="am-btn am-btn--primary">{{ $hero['cta_primary']['label'] }}</a>
            @endif
            @if(!empty($hero['cta_secondary']['href']))
            <a href="{{ $hero['cta_secondary']['href'] }}" class="am-btn am-btn--outline am-btn--light">{{ $hero['cta_secondary']['label'] }}</a>
            @endif
        </div>
    </div>
</section>

{{-- 2. Introduction --}}
@if(!empty($page['intro']))
<section class="am-section am-section--white">
    <div class="am-container am-corten-intro">
        <h2 class="am-corten-section__title">{{ $page['intro']['title'] }}</h2>
        <p class="am-corten-section__lead">{{ $page['intro']['body'] }}</p>
    </div>
</section>
@endif

{{-- 3. Applications --}}
@if(!empty($page['applications']['items']))
<section class="am-section am-section--dark" id="corten-applications">
    <div class="am-container">
        <div class="am-section-head am-section-head--left">
            <h2>{{ $page['applications']['title'] }}</h2>
        </div>
        <div class="am-corten-apps">
            @foreach($page['applications']['items'] as $app)
            <article class="am-corten-apps__card">
                <div class="am-corten-apps__media">
                    <img src="{{ $app['image'] }}" alt="{{ $app['name'] }}" loading="lazy">
                </div>
                <h3 class="am-corten-apps__name">{{ $app['name'] }}</h3>
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 4. Why Corten --}}
@if(!empty($page['why']['points']))
<section class="am-section am-section--white">
    <div class="am-container am-corten-split">
        <div>
            <h2 class="am-corten-section__title">{{ $page['why']['title'] }}</h2>
            <ul class="am-corten-checklist">
                @foreach($page['why']['points'] as $point)
                <li>{{ $point }}</li>
                @endforeach
            </ul>
        </div>
        <div class="am-corten-split__media">
            <img src="{{ $service->image }}" alt="Corten steel detail" loading="lazy">
        </div>
    </div>
</section>
@endif

{{-- 5. Finish evolution --}}
@if(!empty($page['finish_evolution']['stages']))
<section class="am-section am-section--dark">
    <div class="am-container">
        <h2 class="am-corten-section__title am-corten-section__title--center">{{ $page['finish_evolution']['title'] }}</h2>
        <div class="am-corten-timeline">
            @foreach($page['finish_evolution']['stages'] as $i => $stage)
            <div class="am-corten-timeline__step">
                <div class="am-corten-timeline__media">
                    <img src="{{ $stage['image'] }}" alt="{{ $stage['label'] }}" loading="lazy">
                </div>
                <p class="am-corten-timeline__label">{{ $stage['label'] }}</p>
                @if($i < count($page['finish_evolution']['stages']) - 1)
                <span class="am-corten-timeline__arrow" aria-hidden="true">→</span>
                @endif
            </div>
            @endforeach
        </div>
        @if(!empty($page['finish_evolution']['note']))
        <p class="am-corten-timeline__note">{{ $page['finish_evolution']['note'] }}</p>
        @endif
    </div>
</section>
@endif

{{-- 6. Process --}}
@if(!empty($page['process']['steps']))
<section class="am-section am-section--white">
    <div class="am-container">
        <h2 class="am-corten-section__title am-corten-section__title--center">{{ $page['process']['title'] }}</h2>
        <ol class="am-corten-process">
            @foreach($page['process']['steps'] as $i => $step)
            <li class="am-corten-process__step">
                <span class="am-corten-process__num">{{ $i + 1 }}</span>
                <span class="am-corten-process__text">{{ $step }}</span>
            </li>
            @endforeach
        </ol>
    </div>
</section>
@endif

{{-- 7. Featured projects --}}
@if(!empty($page['featured_projects']['items']))
<section class="am-section am-section--dark">
    <div class="am-container">
        <div class="am-section-head am-section-head--row">
            <div>
                <h2>{{ $page['featured_projects']['title'] }}</h2>
                @if(!empty($page['featured_projects']['categories']))
                <p>{{ implode(' · ', $page['featured_projects']['categories']) }}</p>
                @endif
            </div>
            <a href="{{ route('projects.index') }}" class="am-section-head__link">View all projects →</a>
        </div>
        <div class="am-grid-4 am-corten-projects">
            @foreach($page['featured_projects']['items'] as $project)
            @php
                $href = !empty($project['slug']) ? route('projects.show', $project['slug']) : null;
            @endphp
            @if($href)
            <a href="{{ $href }}" class="am-card am-corten-project">
            @else
            <article class="am-card am-corten-project am-corten-project--static">
            @endif
                <div class="am-card__thumb">
                    <img src="{{ $project['image'] }}" alt="{{ $project['title'] }}" loading="lazy">
                </div>
                <div class="am-card__body">
                    <p class="am-card__label">{{ $project['category'] ?? '' }}@if(!empty($project['location'])) · {{ $project['location'] }}@endif</p>
                    <h3 class="am-card__title">{{ $project['title'] }}</h3>
                </div>
            @if($href)</a>@else</article>@endif
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 8. Technical options --}}
@if(!empty($page['technical']['options']))
<section class="am-section am-section--white">
    <div class="am-container am-corten-split am-corten-split--reverse">
        <div>
            <h2 class="am-corten-section__title">{{ $page['technical']['title'] }}</h2>
            <ul class="am-corten-bullets">
                @foreach($page['technical']['options'] as $opt)
                <li>{{ $opt }}</li>
                @endforeach
            </ul>
        </div>
        <div class="am-corten-split__media">
            <img src="https://images.unsplash.com/photo-1503387762-592deb58ef4e?w=900&q=80" alt="Corten fabrication" loading="lazy">
        </div>
    </div>
</section>
@endif

{{-- 9. Considerations --}}
@if(!empty($page['considerations']['points']))
<section class="am-section am-section--dark am-corten-consider">
    <div class="am-container">
        <h2 class="am-corten-section__title">{{ $page['considerations']['title'] }}</h2>
        <ul class="am-corten-consider__list">
            @foreach($page['considerations']['points'] as $point)
            <li>{{ $point }}</li>
            @endforeach
        </ul>
    </div>
</section>
@endif

{{-- 10. FAQ --}}
@if(!empty($page['faq']['items']))
<section class="am-section am-section--white">
    <div class="am-container am-corten-faq-wrap">
        <h2 class="am-corten-section__title am-corten-section__title--center">{{ $page['faq']['title'] }}</h2>
        <div class="am-corten-faq">
            @foreach($page['faq']['items'] as $item)
            <details class="am-corten-faq__item">
                <summary>{{ $item['q'] }}</summary>
                <p>{{ $item['a'] }}</p>
            </details>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 11. Final CTA + quote form --}}
<section class="am-section am-section--dark am-corten-cta" id="corten-quote">
    <div class="am-container am-corten-cta__grid">
        <div>
            <h2 class="am-corten-section__title">{{ $page['cta']['title'] ?? 'Get a Custom Quote' }}</h2>
            <p class="am-corten-section__lead">{{ $page['cta']['body'] ?? '' }}</p>
            <div class="am-corten-hero__actions">
                @if(!empty($page['cta']['secondary']['href']))
                <a href="{{ url($page['cta']['secondary']['href']) }}" class="am-btn am-btn--outline am-btn--light">{{ $page['cta']['secondary']['label'] }}</a>
                @endif
            </div>
        </div>
        <div class="am-card am-corten-quote-card">
            <div class="am-card__body">
                <p class="am-card__label">Custom Corten enquiry</p>
                <h3 class="am-card__title" style="font-size:1.25rem;margin-bottom:1rem">Request a Quote</h3>
                <x-lead-form-inline
                    :service-slug="$service->slug"
                    :subject="'Corten steel — custom quote request'"
                    :show-budget="true" />
            </div>
        </div>
    </div>
</section>

@endsection
