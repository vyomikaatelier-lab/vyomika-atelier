@props([
    'title',
    'metaTitle' => null,
    'metaDescription' => null,
    'lastUpdated' => null,
    'breadcrumbs' => [],
    'sections' => [],
    'business' => [],
])

@extends('layouts.store')

@section('title', $metaTitle ?? ($title . ' — ' . (config('legal.business.brand_name') ?? 'Vyomika Atelier LLP')))

@push('meta')
@if($metaDescription)
<meta name="description" content="{{ $metaDescription }}">
@endif
@endpush

@section('content')
@if(count($breadcrumbs))
<nav class="am-breadcrumbs am-breadcrumbs--legal" aria-label="Breadcrumb">
    <div class="am-container">
        @foreach($breadcrumbs as $i => $item)
            @if($i > 0)<span class="am-breadcrumbs__sep">/</span>@endif
            @if(!empty($item['url']) && $i < count($breadcrumbs) - 1)
                <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
            @else
                <span @if($i === count($breadcrumbs) - 1) aria-current="page" @endif>{{ $item['label'] }}</span>
            @endif
        @endforeach
    </div>
</nav>
@endif

@include('partials.am-page-hero', [
    'label' => 'Legal',
    'title' => $title,
    'subtitle' => $lastUpdated ? 'Last updated: ' . $lastUpdated : null,
])

<section class="am-page-body am-page-body--narrow am-legal-page">
    <div class="am-container">
        <div class="am-prose am-legal-prose">
            @foreach($sections as $section)
                <h2 class="am-legal-prose__heading">{{ $section['heading'] }}</h2>
                @foreach($section['paragraphs'] as $paragraph)
                    <p>{!! preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $paragraph) !!}</p>
                @endforeach
            @endforeach

            <div class="am-legal-prose__cta">
                @include('partials.am-business-details', ['business' => $business])
                <p>Questions? <a href="{{ route('contact.index') }}">Contact us</a> or email
                    <a href="mailto:{{ $business['email'] ?? '' }}">{{ $business['email'] ?? '[email]' }}</a>.</p>
            </div>
        </div>
    </div>
</section>
@endsection
