@extends('layouts.store')

@section('title', $project->seoTitle())

@push('meta')
<meta name="description" content="{{ $project->seoDescription() }}">
@endpush

@section('content')

<nav class="am-breadcrumbs am-breadcrumbs--legal" aria-label="Breadcrumb">
    <div class="am-container">
        <a href="{{ route('home') }}">Home</a><span class="am-breadcrumbs__sep">/</span>
        <a href="{{ route('projects.index') }}">Projects</a><span class="am-breadcrumbs__sep">/</span>
        <span aria-current="page">{{ $project->title }}</span>
    </div>
</nav>

@if($project->image)
<section class="am-project-hero">
    <img src="{{ $project->image }}" alt="{{ $project->title }}" class="am-project-hero__img">
</section>
@endif

<section class="am-page-body am-project-detail">
    <div class="am-container">
        <div class="am-project-detail__layout">
            <div class="am-project-detail__main">
                <h1 class="am-project-detail__title">{{ $project->title }}</h1>

                @if($project->summary)
                <p class="am-project-detail__overview">{{ $project->summary }}</p>
                @endif

                @if($project->content)
                <div class="am-prose am-project-detail__content">{!! $project->content !!}</div>
                @endif

                @if($project->design_details)
                <div class="am-project-block">
                    <h2 class="am-project-block__title">Design Details</h2>
                    <div class="am-prose">{!! nl2br(e($project->design_details)) !!}</div>
                </div>
                @endif

                @if($project->materials && count($project->materials))
                <div class="am-project-block">
                    <h2 class="am-project-block__title">Materials &amp; Finishes</h2>
                    <ul class="am-corten-checklist">
                        @foreach($project->materials as $material)
                        <li>{{ $material }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                @if($project->gallery && count($project->gallery))
                <div class="am-project-block">
                    <h2 class="am-project-block__title">Gallery</h2>
                    <div class="am-project-gallery">
                        @foreach($project->gallery as $image)
                        <figure class="am-project-gallery__item">
                            <img src="{{ $image }}" alt="{{ $project->title }} detail" loading="lazy">
                        </figure>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($project->hasTestimonial())
                <blockquote class="am-project-testimonial">
                    <p class="am-project-testimonial__quote">"{{ $project->testimonial_quote }}"</p>
                    <footer>
                        <cite class="am-project-testimonial__author">{{ $project->testimonial_author }}</cite>
                        @if($project->testimonial_role)
                        <span class="am-project-testimonial__role">{{ $project->testimonial_role }}</span>
                        @endif
                    </footer>
                </blockquote>
                @endif

                <div class="am-project-detail__cta">
                    <button type="button"
                        class="am-btn am-btn--primary"
                        data-open-project-enquiry
                        data-project-slug="{{ $project->slug }}"
                        data-project-title="{{ $project->title }}"
                        @if($project->image) data-project-image="{{ $project->image }}" @endif>
                        Inquire About a Similar Project
                    </button>
                    <a href="{{ route('projects.index') }}" class="am-btn am-btn--outline">← All Projects</a>
                </div>
            </div>

            <aside class="am-project-sidebar">
                <dl class="am-project-meta">
                    @if($project->client)
                    <div>
                        <dt>Client</dt>
                        <dd>{{ $project->client }}</dd>
                    </div>
                    @endif
                    @if($project->location)
                    <div>
                        <dt>Location</dt>
                        <dd>{{ $project->location }}</dd>
                    </div>
                    @endif
                    @if($project->completed_at)
                    <div>
                        <dt>Year</dt>
                        <dd>{{ $project->completed_at->format('Y') }}</dd>
                    </div>
                    @endif
                    @if($project->categoryLabel())
                    <div>
                        <dt>Category</dt>
                        <dd>{{ $project->categoryLabel() }}</dd>
                    </div>
                    @endif
                </dl>
                <div class="am-card" style="margin-top:1.5rem">
                    <div class="am-card__body">
                        <p class="am-card__label">Similar project?</p>
                        <h3 class="am-card__title" style="font-size:1.1rem;margin-bottom:0.75rem">Get a Quote</h3>
                        <p class="am-card__text" style="margin-bottom:1rem">Share dimensions, finishes and timeline — we respond within 24 hours.</p>
                        <button type="button" class="am-btn am-btn--primary am-btn--full am-btn--sm" data-open-contact-studio data-contact-context="Quote — {{ $project->title }}">Contact Studio</button>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>

@endsection
