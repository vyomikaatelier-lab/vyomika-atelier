@extends('layouts.store')

@php
    use App\Support\LandingPageContent;
    use App\Support\Seo\JsonLd;

    $page = LandingPageContent::withResolvedImages($page ?? []);
    $hero = $page['hero'] ?? [];
    $categoryItems = LandingPageContent::activeItems($page['categories']['items'] ?? []);
    $layoutItems = LandingPageContent::activeItems($page['layouts']['items'] ?? []);
    $whyItems = $page['why']['items'] ?? [];
    $quote = $page['quote'] ?? [];
    $faqs = LandingPageContent::activeItems($page['faq']['items'] ?? []);
    $processSteps = $page['process']['steps'] ?? [];
@endphp

@section('title', $pageSeo['title'] ?? 'Railings — Vyomika Atelier')

@if($faqs)
@push('jsonld')
{!! JsonLd::script(JsonLd::faqPage($faqs)) !!}
@endpush
@endif

@section('content')

@include('partials.am-shop-category-hero', ['hero' => $hero])

@if(!empty($page['intro']['body']))
<section class="am-section am-section--white">
    <div class="am-container am-railings-intro">
        <h2 class="am-corten-section__title am-corten-section__title--center">{{ $page['intro']['title'] ?? '' }}</h2>
        <p class="am-corten-section__lead am-corten-section__lead--center">{{ $page['intro']['body'] }}</p>
    </div>
</section>
@endif

@if(!empty($categoryItems))
<section class="am-section am-section--cream" id="railing-categories">
    <div class="am-container">
        @include('partials.am-config-design-gallery', [
            'items' => $categoryItems,
            'heading' => $page['categories']['title'] ?? 'Railing Designs',
            'sectionLabel' => 'Design Gallery',
            'serviceSlug' => 'railings',
            'categoryLabel' => 'Railings',
            'quoteAnchor' => '#railing-quote-form',
            'quoteLabel' => 'Order Now',
        ])
    </div>
</section>
@endif

@if(!empty($layoutItems))
<section class="am-section am-section--white" id="railing-layouts">
    <div class="am-container">
        <div class="am-railings-section-head am-railings-section-head--center">
            <h2 class="am-corten-section__title">{{ $page['layouts']['title'] ?? 'Staircase & Layout Shapes' }}</h2>
            @if(!empty($page['layouts']['subtitle']))
            <p class="am-corten-section__lead">{{ $page['layouts']['subtitle'] }}</p>
            @endif
        </div>
        <div class="am-railings-layouts">
            @foreach($layoutItems as $item)
            <article class="am-railings-layout">
                <h3>{{ $item['title'] }}</h3>
                <p>{{ $item['text'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif

@if(!empty($whyItems))
<section class="am-section am-section--dark">
    <div class="am-container am-corten-split">
        <div>
            <h2 class="am-corten-section__title">{{ $page['why']['title'] ?? '' }}</h2>
            <ul class="am-corten-checklist">
                @foreach($whyItems as $item)
                <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
        @if(!empty($page['why']['image']))
        <div class="am-corten-split__media">
            <img src="{{ $page['why']['image'] }}" alt="{{ $page['why']['image_alt'] ?? 'Staircase railing fabrication' }}" loading="lazy">
        </div>
        @endif
    </div>
</section>
@endif

@if(!empty($processSteps))
<section class="am-section am-section--cream">
    <div class="am-container">
        <h2 class="am-corten-section__title am-corten-section__title--center">{{ $page['process']['title'] ?? 'From Measurement to Installation' }}</h2>
        <ol class="am-corten-process">
            @foreach($processSteps as $i => $step)
            <li class="am-corten-process__step">
                <span class="am-corten-process__num">{{ $i + 1 }}</span>
                <span class="am-corten-process__text">{{ is_array($step) ? ($step['title'] ?? $step['text'] ?? '') : $step }}</span>
            </li>
            @endforeach
        </ol>
    </div>
</section>
@endif

@if(!empty($faqs))
<section class="am-section am-section--white">
    <div class="am-container am-corten-faq-wrap">
        <h2 class="am-corten-section__title am-corten-section__title--center">{{ $page['faq']['title'] ?? 'FAQs' }}</h2>
        <div class="am-corten-faq">
            @foreach($faqs as $item)
            <details class="am-corten-faq__item">
                <summary>{{ $item['q'] ?? '' }}</summary>
                <p>{{ $item['a'] ?? '' }}</p>
            </details>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="am-section am-section--white am-railings-quote" id="railing-quote">
    <div class="am-container am-railings-quote__grid">
        <div>
            <h2 class="am-corten-section__title">{{ $quote['title'] ?? 'Request a Quotation' }}</h2>
            <p class="am-corten-section__lead">{{ $quote['body'] ?? '' }}</p>
            @if(!empty($quote['bullets']))
            <ul class="am-corten-bullets am-railings-quote__bullets">
                @foreach($quote['bullets'] as $bullet)
                <li>{{ $bullet }}</li>
                @endforeach
            </ul>
            @endif
        </div>
        <div class="am-card am-pro-form-card" id="railing-quote-form">
            <div class="am-card__body">
                <x-railings-quote-form :form-config="$page['form'] ?? []" />
            </div>
        </div>
    </div>
</section>

@endsection
