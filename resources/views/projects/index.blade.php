@extends('layouts.store')

@php
    $hero = $page['hero'] ?? [];
@endphp

@section('title', $page['meta_title'] ?? 'Projects — Vyomika Atelier')

@push('meta')
<meta name="description" content="{{ $page['meta_description'] ?? '' }}">
@endpush

@section('content')

@include('partials.am-page-hero', [
    'label' => $hero['label'] ?? 'Our Work',
    'title' => $hero['title'] ?? 'Projects',
    'subtitle' => $hero['subtitle'] ?? null,
])

<section class="am-page-body am-projects-index">
    <div class="am-container">
        @if(count($categories))
        <nav class="am-project-filters" aria-label="Filter projects by category">
            @foreach($categories as $cat)
            <a href="{{ route('projects.index', $cat['slug'] ? ['category' => $cat['slug']] : []) }}"
               class="am-project-filters__btn {{ $activeCategory === ($cat['slug'] ?? '') ? 'is-active' : '' }}">
                {{ $cat['label'] }}
            </a>
            @endforeach
        </nav>
        @endif

        @if($projects->isNotEmpty())
        <div class="am-project-grid">
            @foreach($projects as $project)
            <a href="{{ route('projects.show', $project->slug) }}" class="am-project-card">
                <div class="am-project-card__media">
                    @if($project->image)
                    <img src="{{ $project->image }}" alt="{{ $project->title }}" loading="lazy">
                    @endif
                </div>
                <div class="am-project-card__body">
                    <p class="am-project-card__meta">
                        @if($project->categoryLabel())<span>{{ $project->categoryLabel() }}</span>@endif
                        @if($project->location)<span>{{ $project->location }}</span>@endif
                    </p>
                    <h2 class="am-project-card__title">{{ $project->title }}</h2>
                    @if($project->summary)
                    <p class="am-project-card__excerpt">{{ $project->summary }}</p>
                    @endif
                    <span class="am-project-card__link">View project →</span>
                </div>
            </a>
            @endforeach
        </div>
        <div class="am-pagination">{{ $projects->links() }}</div>
        @else
        <div class="am-empty" style="text-align:center;padding:3rem 0">
            <p style="color:var(--am-muted);margin-bottom:1.5rem">No projects in this category yet.</p>
            <a href="{{ route('projects.index') }}" class="am-btn am-btn--outline">View all projects</a>
        </div>
        @endif
    </div>
</section>

@php $cta = $page['footer_cta'] ?? []; @endphp
@if(!empty($cta['title']))
<section class="am-section am-section--dark am-projects-cta">
    <div class="am-container am-projects-cta__inner">
        <div>
            <h2 class="am-corten-section__title">{{ $cta['title'] }}</h2>
            <p class="am-corten-section__lead">{{ $cta['body'] ?? '' }}</p>
        </div>
        <div class="am-projects-cta__actions">
            <a href="{{ route('leads.create') }}" class="am-btn am-btn--primary">{{ $cta['primary_label'] ?? 'Request a Quote' }}</a>
            <button type="button" class="am-btn am-btn--outline am-btn--light" data-open-contact-studio data-contact-context="Project enquiry">{{ $cta['secondary_label'] ?? 'Contact Us' }}</button>
        </div>
    </div>
</section>
@endif

@endsection
