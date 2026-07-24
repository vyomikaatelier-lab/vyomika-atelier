@extends('layouts.store')

@php
    use App\Support\LandingPageContent;
    use App\Support\Seo\JsonLd;
    use App\Support\Seo\PageSeo;

    $page = LandingPageContent::withResolvedImages($page ?? \App\Support\CortenContent::all());
    $hero = $page['hero'] ?? [];
    $heroImg = $hero['image'] ?? ($service->image ? \App\Support\MediaUrl::resolve($service->image) : '');
    $apps = LandingPageContent::activeItems($page['applications']['items'] ?? []);
    $stages = LandingPageContent::activeItems($page['finish_evolution']['stages'] ?? []);
    $projects = LandingPageContent::activeItems($page['featured_projects']['items'] ?? []);
    $faqs = LandingPageContent::activeItems($page['faq']['items'] ?? []);
    $pageSeo = PageSeo::make([
        'title' => $page['meta_title'] ?? $service->meta_title ?? \App\Support\CortenContent::metaTitle(),
        'description' => $page['meta_description'] ?? $service->meta_description ?? \App\Support\CortenContent::metaDescription(),
        'canonical' => route('corten-steel.show'),
        'og_image' => $heroImg,
        'primary_keyword' => $page['primary_keyword'] ?? null,
    ]);
@endphp

@section('title', $pageSeo['title'])

@if($faqs)
@push('jsonld')
{!! JsonLd::script(JsonLd::faqPage($faqs)) !!}
@endpush
@endif

@section('content')

<section class="am-corten-hero" style="--corten-hero-img: url('{{ $heroImg }}')">
    <div class="am-corten-hero__overlay"></div>
    <div class="am-container am-corten-hero__inner">
        @if(!empty($hero['label']))
        <p class="am-page-hero__label">{{ $hero['label'] }}</p>
        @endif
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

@if(!empty($page['intro']['body']))
<section class="am-section am-section--white">
    <div class="am-container am-corten-intro">
        <h2 class="am-corten-section__title">{{ $page['intro']['title'] ?? '' }}</h2>
        <p class="am-corten-section__lead">{{ $page['intro']['body'] }}</p>
    </div>
</section>
@endif

@if(!empty($apps))
<section class="am-section am-section--dark" id="corten-applications">
    <div class="am-container">
        <div class="am-section-head am-section-head--left">
            <h2>{{ $page['applications']['title'] ?? 'Applications' }}</h2>
        </div>
        <div class="am-corten-apps">
            @foreach($apps as $app)
            <article class="am-corten-apps__card">
                @if(!empty($app['image']))
                <div class="am-corten-apps__media">
                    <img src="{{ $app['image'] }}" alt="{{ $app['image_alt'] ?? $app['name'] ?? '' }}" loading="lazy">
                </div>
                @endif
                <h3 class="am-corten-apps__name">{{ $app['name'] ?? '' }}</h3>
                @if(!empty($app['text']))
                <p class="am-corten-apps__desc">{{ $app['text'] }}</p>
                @endif
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif

@if(!empty($page['why']['points']))
<section class="am-section am-section--white">
    <div class="am-container am-corten-split">
        <div>
            <h2 class="am-corten-section__title">{{ $page['why']['title'] ?? '' }}</h2>
            <ul class="am-corten-checklist">
                @foreach($page['why']['points'] as $point)
                <li>{{ $point }}</li>
                @endforeach
            </ul>
        </div>
        @if(!empty($page['why']['image']))
        <div class="am-corten-split__media">
            <img src="{{ $page['why']['image'] }}" alt="{{ $page['why']['image_alt'] ?? 'Corten steel detail' }}" loading="lazy">
        </div>
        @endif
    </div>
</section>
@endif

@if(!empty($stages))
<section class="am-section am-section--dark">
    <div class="am-container">
        <h2 class="am-corten-section__title am-corten-section__title--center">{{ $page['finish_evolution']['title'] ?? '' }}</h2>
        <div class="am-corten-timeline">
            @foreach($stages as $i => $stage)
            <div class="am-corten-timeline__step">
                @if(!empty($stage['image']))
                <div class="am-corten-timeline__media">
                    <img src="{{ $stage['image'] }}" alt="{{ $stage['image_alt'] ?? $stage['label'] ?? '' }}" loading="lazy">
                </div>
                @endif
                <p class="am-corten-timeline__label">{{ $stage['label'] ?? '' }}</p>
                @if(!empty($stage['text']))
                <p class="am-corten-timeline__desc">{{ $stage['text'] }}</p>
                @endif
                @if($i < count($stages) - 1)
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

@if(!empty($page['process']['steps']))
<section class="am-section am-section--white">
    <div class="am-container">
        <h2 class="am-corten-section__title am-corten-section__title--center">{{ $page['process']['title'] ?? '' }}</h2>
        <ol class="am-corten-process">
            @foreach($page['process']['steps'] as $i => $step)
            <li class="am-corten-process__step">
                <span class="am-corten-process__num">{{ $i + 1 }}</span>
                <span class="am-corten-process__text">{{ is_array($step) ? ($step['title'] ?? $step['text'] ?? '') : $step }}</span>
            </li>
            @endforeach
        </ol>
    </div>
</section>
@endif

@if(!empty($projects))
<section class="am-section am-section--dark">
    <div class="am-container">
        <div class="am-section-head am-section-head--row">
            <div>
                <h2>{{ $page['featured_projects']['title'] ?? 'Projects' }}</h2>
                @if(!empty($page['featured_projects']['categories']))
                <p>{{ implode(' · ', $page['featured_projects']['categories']) }}</p>
                @endif
            </div>
            <a href="{{ route('projects.index') }}" class="am-section-head__link">View all projects →</a>
        </div>
        <div class="am-grid-4 am-corten-projects">
            @foreach($projects as $project)
            @php
                $href = !empty($project['slug']) ? route('projects.show', $project['slug']) : null;
            @endphp
            @if($href)
            <a href="{{ $href }}" class="am-card am-corten-project">
            @else
            <article class="am-card am-corten-project am-corten-project--static">
            @endif
                <div class="am-card__thumb">
                    @if(!empty($project['image']))
                    <img src="{{ $project['image'] }}" alt="{{ $project['image_alt'] ?? $project['title'] ?? '' }}" loading="lazy">
                    @endif
                </div>
                <div class="am-card__body">
                    <p class="am-card__label">{{ $project['category'] ?? '' }}@if(!empty($project['location'])) · {{ $project['location'] }}@endif</p>
                    <h3 class="am-card__title">{{ $project['title'] ?? '' }}</h3>
                </div>
            @if($href)</a>@else</article>@endif
            @endforeach
        </div>
    </div>
</section>
@endif

@if(!empty($page['technical']['options']))
<section class="am-section am-section--white">
    <div class="am-container am-corten-split am-corten-split--reverse">
        <div>
            <h2 class="am-corten-section__title">{{ $page['technical']['title'] ?? '' }}</h2>
            <ul class="am-corten-bullets">
                @foreach($page['technical']['options'] as $opt)
                <li>{{ $opt }}</li>
                @endforeach
            </ul>
        </div>
        @if(!empty($page['technical']['image']))
        <div class="am-corten-split__media">
            <img src="{{ $page['technical']['image'] }}" alt="{{ $page['technical']['image_alt'] ?? 'Corten fabrication' }}" loading="lazy">
        </div>
        @endif
    </div>
</section>
@endif

@if(!empty($page['considerations']['points']))
<section class="am-section am-section--dark am-corten-consider">
    <div class="am-container">
        <h2 class="am-corten-section__title">{{ $page['considerations']['title'] ?? '' }}</h2>
        <ul class="am-corten-consider__list">
            @foreach($page['considerations']['points'] as $point)
            <li>{{ $point }}</li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@if(!empty($faqs))
<section class="am-section am-section--white">
    <div class="am-container am-corten-faq-wrap">
        <h2 class="am-corten-section__title am-corten-section__title--center">{{ $page['faq']['title'] ?? 'FAQ' }}</h2>
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
                <p class="am-card__label">{{ $page['cta']['form_label'] ?? 'Custom Corten enquiry' }}</p>
                <h3 class="am-card__title" style="font-size:1.25rem;margin-bottom:1rem">{{ $page['cta']['form_title'] ?? 'Request a Quote' }}</h3>
                <x-lead-form-inline
                    :service-slug="$service->slug"
                    :subject="'Corten steel — custom quote request'"
                    :show-budget="true" />
            </div>
        </div>
    </div>
</section>

@endsection
