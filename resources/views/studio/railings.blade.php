@extends('layouts.store')



@php

    $hero = $page['hero'] ?? [];

@endphp



@section('title', $page['meta_title'] ?? 'Railings — Vyomika Atelier LLP')



@push('meta')

<meta name="description" content="{{ $page['meta_description'] ?? '' }}">

<link rel="canonical" href="{{ route('railings.index') }}">

@endpush



@section('content')



<section class="am-railings-hero" style="--railings-hero-img: url('{{ $hero['image'] ?? '' }}')">

    <div class="am-container am-railings-hero__inner">

        <h1 class="am-railings-hero__title">{{ $hero['title'] ?? 'Railings' }}</h1>

        <p class="am-railings-hero__subtitle">{{ $hero['subtitle'] ?? '' }}</p>

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



@if(!empty($page['intro']['body']))

<section class="am-section am-section--white">

    <div class="am-container am-railings-intro">

        <h2 class="am-corten-section__title am-corten-section__title--center">{{ $page['intro']['title'] ?? '' }}</h2>

        <p class="am-corten-section__lead am-corten-section__lead--center">{{ $page['intro']['body'] }}</p>

    </div>

</section>

@endif



@if(!empty($page['categories']['items']))

<section class="am-section am-section--cream" id="railing-categories">

    <div class="am-container">

        @include('partials.am-config-design-gallery', [

            'items' => $page['categories']['items'],

            'heading' => $page['categories']['title'] ?? 'Railing Designs',

            'sectionLabel' => 'Design Gallery',

            'serviceSlug' => 'railings',

            'categoryLabel' => 'Railings',

        ])

    </div>

</section>

@endif

@if(!empty($page['layouts']['items']))

<section class="am-section am-section--white" id="railing-layouts">

    <div class="am-container">

        <div class="am-railings-section-head am-railings-section-head--center">

            <h2 class="am-corten-section__title">{{ $page['layouts']['title'] }}</h2>

            @if(!empty($page['layouts']['subtitle']))

            <p class="am-corten-section__lead">{{ $page['layouts']['subtitle'] }}</p>

            @endif

        </div>

        <div class="am-railings-layouts">

            @foreach($page['layouts']['items'] as $item)

            <article class="am-railings-layout">

                <h3>{{ $item['title'] }}</h3>

                <p>{{ $item['text'] }}</p>

            </article>

            @endforeach

        </div>

    </div>

</section>

@endif



@if(!empty($page['why']['items']))

<section class="am-section am-section--dark">

    <div class="am-container am-corten-split">

        <div>

            <h2 class="am-corten-section__title">{{ $page['why']['title'] }}</h2>

            <ul class="am-corten-checklist">

                @foreach($page['why']['items'] as $item)

                <li>{{ $item }}</li>

                @endforeach

            </ul>

        </div>

        <div class="am-corten-split__media">

            <img src="https://images.unsplash.com/photo-1600607687920-4e3a09aebb82?w=900&q=80" alt="Staircase railing fabrication" loading="lazy">

        </div>

    </div>

</section>

@endif



<section class="am-section am-section--white am-railings-quote" id="railing-quote">

    <div class="am-container am-railings-quote__grid">

        <div>

            <h2 class="am-corten-section__title">Request a Quotation</h2>

            <p class="am-corten-section__lead">Tell us about your staircase, material preferences and site location. Attach a photo or drawing if you have one — we will respond with timelines and an indicative quote.</p>

            <ul class="am-corten-bullets am-railings-quote__bullets">

                <li>Site measurement available in Mumbai metro</li>

                <li>Shop drawings shared before fabrication</li>

                <li>Glass, stainless, MS and wrought iron systems</li>

            </ul>

        </div>

        <div class="am-card am-pro-form-card">

            <div class="am-card__body">

                <x-railings-quote-form :form-config="$page['form'] ?? []" />

            </div>

        </div>

    </div>

</section>



@endsection

