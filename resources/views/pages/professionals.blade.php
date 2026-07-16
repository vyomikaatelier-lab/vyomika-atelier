@extends('layouts.store')

@php
    $hero = $page['hero'] ?? [];
@endphp

@section('title', $page['meta_title'] ?? 'Professionals — Vyomika Atelier LLP')

@push('meta')
<meta name="description" content="{{ $page['meta_description'] ?? '' }}">
@endpush

@section('content')

{{-- Hero --}}
<section class="am-pro-hero" style="--pro-hero-img: url('{{ $hero['image'] ?? '' }}')">
    <div class="am-container am-pro-hero__inner">
        <p class="am-page-hero__label">{{ $hero['label'] ?? 'Professionals' }}</p>
        <h1 class="am-pro-hero__title">{{ $hero['title'] ?? 'Partner with Us' }}</h1>
        <p class="am-pro-hero__subtitle">{{ $hero['subtitle'] ?? '' }}</p>
        @if(!empty($hero['highlights']))
        <ul class="am-pro-hero__highlights">
            @foreach($hero['highlights'] as $item)
            <li>{{ $item }}</li>
            @endforeach
        </ul>
        @endif
        <div class="am-pro-hero__actions">
            @if(!empty($hero['cta_primary']['href']))
            <a href="{{ $hero['cta_primary']['href'] }}" class="am-btn am-btn--primary">{{ $hero['cta_primary']['label'] }}</a>
            @endif
            @if(!empty($hero['cta_secondary']['href']))
            <a href="{{ $hero['cta_secondary']['href'] }}" class="am-btn am-btn--outline am-btn--light">{{ $hero['cta_secondary']['label'] }}</a>
            @endif
        </div>
    </div>
</section>

{{-- Who can apply --}}
@if(!empty($page['who_can_apply']['items']))
<section class="am-section am-section--white" id="who-can-apply">
    <div class="am-container">
        <h2 class="am-corten-section__title am-corten-section__title--center">{{ $page['who_can_apply']['title'] }}</h2>
        <div class="am-pro-audience">
            @foreach($page['who_can_apply']['items'] as $item)
            <article class="am-pro-audience__card">
                <h3>{{ $item['title'] }}</h3>
                <p>{{ $item['text'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Benefits --}}
@if(!empty($page['benefits']['items']))
<section class="am-section am-section--dark" id="partnership-benefits">
    <div class="am-container am-corten-split">
        <div>
            <h2 class="am-corten-section__title">{{ $page['benefits']['title'] }}</h2>
            <ul class="am-corten-checklist">
                @foreach($page['benefits']['items'] as $item)
                <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
        <div class="am-corten-split__media">
            <img src="https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=900&q=80" alt="Professional metalwork" loading="lazy">
        </div>
    </div>
</section>
@endif

{{-- Categories --}}
@if(!empty($page['categories']['items']))
<section class="am-section am-section--white">
    <div class="am-container">
        <h2 class="am-corten-section__title am-corten-section__title--center">{{ $page['categories']['title'] }}</h2>
        <div class="am-pro-tags">
            @foreach($page['categories']['items'] as $item)
            <span class="am-pro-tags__item">{{ $item }}</span>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Process --}}
@if(!empty($page['process']['steps']))
<section class="am-section am-section--dark">
    <div class="am-container">
        <h2 class="am-corten-section__title am-corten-section__title--center">{{ $page['process']['title'] }}</h2>
        <div class="am-pro-steps">
            @foreach($page['process']['steps'] as $i => $step)
            <article class="am-pro-steps__item">
                <span class="am-pro-steps__num">{{ $i + 1 }}</span>
                <h3>{{ $step['title'] }}</h3>
                <p>{{ $step['text'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Application form --}}
<section class="am-section am-section--white am-pro-apply" id="professional-apply">
    <div class="am-container am-pro-apply__grid">
        <div>
            <h2 class="am-corten-section__title">Professional Application</h2>
            <p class="am-corten-section__lead">Tell us about your practice and project focus. Fields marked * are required.</p>
            @if(!empty($page['pricing']['items']))
            <div class="am-pro-side-note">
                <h3>{{ $page['pricing']['title'] }}</h3>
                <ul>
                    @foreach($page['pricing']['items'] as $item)
                    <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
        <div class="am-card am-pro-form-card">
            <div class="am-card__body">
                <x-professional-application-form :form-config="$page['form'] ?? []" />
            </div>
        </div>
    </div>
</section>

{{-- Partnership types --}}
@if(!empty($page['partnership_types']['items']))
<section class="am-section am-section--dark">
    <div class="am-container">
        <h2 class="am-corten-section__title am-corten-section__title--center">{{ $page['partnership_types']['title'] }}</h2>
        <div class="am-pro-types">
            @foreach($page['partnership_types']['items'] as $item)
            <article class="am-pro-types__card">
                <h3>{{ $item['title'] }}</h3>
                <p>{{ $item['text'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Dealer support --}}
@if(!empty($page['dealer_support']['items']))
<section class="am-section am-section--white">
    <div class="am-container am-corten-split am-corten-split--reverse">
        <div>
            <h2 class="am-corten-section__title">{{ $page['dealer_support']['title'] }}</h2>
            <ul class="am-corten-bullets">
                @foreach($page['dealer_support']['items'] as $item)
                <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
        <div class="am-corten-split__media">
            <img src="https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=900&q=80" alt="Showroom samples" loading="lazy">
        </div>
    </div>
</section>
@endif

{{-- Why partner --}}
@if(!empty($page['why_partner']['items']))
<section class="am-section am-section--dark">
    <div class="am-container">
        <h2 class="am-corten-section__title am-corten-section__title--center">{{ $page['why_partner']['title'] }}</h2>
        <div class="am-pro-why">
            @foreach($page['why_partner']['items'] as $item)
            <article>
                <h3>{{ $item['title'] }}</h3>
                <p>{{ $item['text'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Featured projects --}}
@if($featuredProjects->isNotEmpty())
<section class="am-section am-section--white">
    <div class="am-container">
        <div class="am-section-head am-section-head--row">
            <div>
                <h2>{{ $page['featured_projects']['title'] ?? 'Professional Projects' }}</h2>
                @if(!empty($page['featured_projects']['subtitle']))
                <p>{{ $page['featured_projects']['subtitle'] }}</p>
                @endif
            </div>
            <a href="{{ route('projects.index') }}" class="am-section-head__link">View all projects →</a>
        </div>
        <div class="am-project-grid">
            @foreach($featuredProjects as $project)
            <a href="{{ route('projects.show', $project->slug) }}" class="am-project-card">
                <div class="am-project-card__media">
                    @if($project->image)<img src="{{ $project->image }}" alt="{{ $project->title }}" loading="lazy">@endif
                </div>
                <div class="am-project-card__body">
                    <p class="am-project-card__meta">
                        @if($project->categoryLabel())<span>{{ $project->categoryLabel() }}</span>@endif
                        @if($project->location)<span>{{ $project->location }}</span>@endif
                    </p>
                    <h3 class="am-project-card__title">{{ $project->title }}</h3>
                </div>
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- FAQ --}}
@if(!empty($page['faq']['items']))
<section class="am-section am-section--dark">
    <div class="am-container am-corten-faq-wrap">
        <h2 class="am-corten-section__title am-corten-section__title--center">{{ $page['faq']['title'] }}</h2>
        <div class="am-corten-faq am-corten-faq--light">
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

{{-- Final CTA --}}
@php $cta = $page['final_cta'] ?? []; @endphp
@if(!empty($cta['title']))
<section class="am-section am-section--white am-pro-final-cta">
    <div class="am-container am-pro-final-cta__inner">
        <div>
            <h2 class="am-corten-section__title">{{ $cta['title'] }}</h2>
            <p class="am-corten-section__lead">{{ $cta['body'] ?? '' }}</p>
            @if(!empty($cta['highlights']))
            <ul class="am-pro-hero__highlights am-pro-hero__highlights--dark">
                @foreach($cta['highlights'] as $h)<li>{{ $h }}</li>@endforeach
            </ul>
            @endif
        </div>
        <a href="#professional-apply" class="am-btn am-btn--primary am-btn--lg">Register Now</a>
    </div>
</section>
@endif

@endsection
