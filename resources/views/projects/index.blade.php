@extends('layouts.store')

@section('title', $pageSeo['title'] ?? 'Projects — Vyomika Atelier')

@section('content')

@include('partials.am-page-hero', [
    'label' => $pageContent['hero']['label'] ?? 'Our Work',
    'title' => $pageContent['h1'] ?? ($pageContent['hero']['title'] ?? 'Projects'),
    'subtitle' => $pageContent['intro'] ?? ($pageContent['hero']['subtitle'] ?? null),
])

<section class="am-page-body am-projects-index">
    <div class="am-container">
        @if($projects->isNotEmpty())
        <div class="am-work-gallery">
            @foreach($projects as $project)
            <article class="am-work-gallery__item">
                @if($project->imageUrl())
                <button type="button"
                    class="am-work-gallery__media"
                    data-work-lightbox
                    data-work-lightbox-src="{{ $project->imageUrl() }}"
                    data-work-lightbox-caption="{{ $project->displayAlt() }}"
                    aria-label="View larger image: {{ $project->project_name }}">
                    <img src="{{ $project->imageUrl() }}" alt="{{ $project->displayAlt() }}" loading="lazy">
                </button>
                @else
                <div class="am-work-gallery__media am-work-gallery__media--empty" aria-hidden="true"></div>
                @endif
                <div class="am-work-gallery__body">
                    <h2 class="am-work-gallery__name">{{ $project->project_name }}</h2>
                    <dl class="am-work-gallery__meta">
                        @if($project->work_type)
                        <div><dt>Work</dt><dd>{{ $project->work_type }}</dd></div>
                        @endif
                        @if($project->city)
                        <div><dt>City</dt><dd>{{ $project->city }}</dd></div>
                        @endif
                        @if($project->client)
                        <div><dt>Client</dt><dd>{{ $project->client }}</dd></div>
                        @endif
                        @if($project->size)
                        <div><dt>Size</dt><dd>{{ $project->size }}</dd></div>
                        @endif
                        @if($project->price)
                        <div><dt>Price</dt><dd>{{ $project->price }}</dd></div>
                        @endif
                    </dl>
                    @if($project->description)
                    <p class="am-work-gallery__desc">{{ $project->description }}</p>
                    @endif
                </div>
            </article>
            @endforeach
        </div>
        @else
        <div class="am-empty" style="text-align:center;padding:3rem 0">
            <p style="color:var(--am-muted);margin-bottom:1.5rem">Project gallery items will appear here once published.</p>
            <button type="button" class="am-btn am-btn--outline" data-open-contact-studio data-contact-context="Project enquiry">Contact Studio</button>
        </div>
        @endif
    </div>
</section>

@php $cta = config('projects.footer_cta', []); @endphp
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

<div class="am-work-lightbox" id="am-work-lightbox" aria-hidden="true" role="dialog" aria-label="Project image">
    <button type="button" class="am-work-lightbox__close" data-work-lightbox-close aria-label="Close">&times;</button>
    <figure class="am-work-lightbox__figure">
        <img src="" alt="" class="am-work-lightbox__img" id="am-work-lightbox-img">
        <figcaption class="am-work-lightbox__caption" id="am-work-lightbox-caption"></figcaption>
    </figure>
</div>

@endsection
