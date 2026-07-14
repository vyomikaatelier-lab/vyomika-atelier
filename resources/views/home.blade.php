@extends('layouts.app')

@section('title', 'VYOMIKA ATELIER — Metal Fabrication & Home Decor')

@section('content')

{{-- CreativeCo-inspired hero: dark stage + fanned cards + accent headline --}}
<section class="va-hero-showcase">
    <div class="max-w-7xl mx-auto px-5">
        <div class="va-hero-stage">
            <div class="grid lg:grid-cols-2 gap-8 items-end relative min-h-[420px] lg:min-h-[380px]">
                <div class="relative z-10 pb-4 lg:pb-8">
                    <p class="va-label text-brand-200 mb-5 va-hero-sub">Architectural Metal &amp; Home Decor</p>
                    <h1 class="va-hero-title mb-6">
                        We craft spaces that are <span class="va-text-accent">precise, enduring,</span> and built to last.
                    </h1>
                    <p class="va-hero-sub text-base md:text-lg font-light leading-relaxed mb-10 max-w-md">
                        Partitions, Corten façades, slim profile doors, bespoke metal furniture, PVD finishes — and curated home decor.
                    </p>
                    <div class="va-hero-cta flex flex-col sm:flex-row gap-4">
                        <a href="{{ route('services.index') }}" class="va-btn-primary bg-white text-brand-900 hover:bg-brand-100">Our Services</a>
                        <a href="{{ route('shop.index') }}" class="va-btn-outline border-white text-white hover:bg-white hover:text-brand-900">Shop Products</a>
                    </div>
                </div>

                @php
                    $fanItems = collect();
                    if (isset($featuredProjects) && $featuredProjects->isNotEmpty()) {
                        $fanItems = $featuredProjects->take(5);
                    } elseif (isset($featuredServices) && $featuredServices->isNotEmpty()) {
                        $fanItems = $featuredServices->take(5);
                    }
                @endphp

                @if($fanItems->isNotEmpty())
                <div class="va-card-fan" aria-label="Featured work">
                    @foreach($fanItems as $item)
                        @php
                            $url = isset($item->slug) && isset($item->title)
                                ? route('projects.show', $item->slug)
                                : (isset($item->slug) ? route('services.show', $item->slug) : '#');
                            $label = $item->title ?? $item->name ?? 'Work';
                            $img = $item->image ?? '';
                        @endphp
                        <a href="{{ $url }}" class="va-fan-card block">
                            @if($img)<img src="{{ $img }}" alt="{{ $label }}">@endif
                            <span>{{ Str::limit($label, 22) }}</span>
                        </a>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</section>

<div class="va-marquee" aria-hidden="true">
    <div class="va-marquee-track">
        <span>Partitions</span><span>Corten Façades</span><span>Slim Profile Doors</span><span>PVD Finishes</span><span>Bespoke Metal</span><span>Home Decor</span>
        <span>Partitions</span><span>Corten Façades</span><span>Slim Profile Doors</span><span>PVD Finishes</span><span>Bespoke Metal</span><span>Home Decor</span>
    </div>
</div>

<section class="max-w-7xl mx-auto px-5 py-20 va-reveal">
    <div class="va-process-grid va-reveal-stagger">
        <div style="--stagger:0" class="va-process-step">
            <p class="va-process-num">01</p>
            <h3 class="font-serif text-xl text-brand-900 mb-2">Consult</h3>
            <p class="text-sm text-brand-500 leading-relaxed">Share dimensions, materials, and vision — we align on scope and feasibility.</p>
        </div>
        <div style="--stagger:1" class="va-process-step">
            <p class="va-process-num">02</p>
            <h3 class="font-serif text-xl text-brand-900 mb-2">Calculate</h3>
            <p class="text-sm text-brand-500 leading-relaxed">Use our sq ft calculator for instant estimates on partitions and metalwork.</p>
        </div>
        <div style="--stagger:2" class="va-process-step">
            <p class="va-process-num">03</p>
            <h3 class="font-serif text-xl text-brand-900 mb-2">Fabricate</h3>
            <p class="text-sm text-brand-500 leading-relaxed">Precision metal fabrication with PVD, glass, and Corten finishes.</p>
        </div>
        <div style="--stagger:3" class="va-process-step">
            <p class="va-process-num">04</p>
            <h3 class="font-serif text-xl text-brand-900 mb-2">Install</h3>
            <p class="text-sm text-brand-500 leading-relaxed">Delivered and installed — built for lasting performance and beauty.</p>
        </div>
    </div>
</section>

@if(isset($featuredServices) && $featuredServices->isNotEmpty())
<section class="max-w-7xl mx-auto px-5 py-24 va-reveal">
    <div class="text-center mb-16">
        <p class="va-label mb-3">What We Do</p>
        <h2 class="font-serif text-4xl text-brand-900">Services</h2>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8 va-reveal-stagger">
        @foreach($featuredServices as $i => $service)
        <a href="{{ route('services.show', $service->slug) }}" class="va-card group block" style="--stagger:{{ $i }}">
            <div class="aspect-[4/3] bg-brand-100 overflow-hidden mb-4 rounded-lg">
                @if($service->image)
                    <img src="{{ $service->image }}" alt="{{ $service->name }}" class="w-full h-full object-cover">
                @endif
            </div>
            <h3 class="font-serif text-xl text-brand-900 group-hover:text-brand-500 transition">{{ $service->name }}</h3>
            <p class="text-sm text-brand-500 mt-2">{{ Str::limit($service->summary, 90) }}</p>
        </a>
        @endforeach
    </div>
    <div class="text-center mt-12">
        <a href="{{ route('services.index') }}" class="va-btn-outline">All Services</a>
    </div>
</section>
@endif

@if(isset($featuredProjects) && $featuredProjects->isNotEmpty())
<section class="bg-white py-24 va-reveal">
    <div class="max-w-7xl mx-auto px-5">
        <div class="text-center mb-16">
            <p class="va-label mb-3">Our Work</p>
            <h2 class="font-serif text-4xl text-brand-900">Featured Projects</h2>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 va-reveal-stagger">
            @foreach($featuredProjects as $i => $project)
            <a href="{{ route('projects.show', $project->slug) }}" class="group relative overflow-hidden aspect-[3/4] rounded-lg" style="--stagger:{{ $i }}">
                @if($project->image)
                    <img src="{{ $project->image }}" alt="{{ $project->title }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                @endif
                <div class="absolute inset-0 bg-brand-900/30 group-hover:bg-brand-900/50 transition"></div>
                <div class="absolute bottom-0 left-0 right-0 p-4 text-white">
                    @if($project->location)<p class="text-[10px] uppercase tracking-[0.2em] text-brand-200 mb-1">{{ $project->location }}</p>@endif
                    <h3 class="font-serif text-lg">{{ $project->title }}</h3>
                </div>
            </a>
            @endforeach
        </div>
        <div class="text-center mt-12">
            <a href="{{ route('projects.index') }}" class="va-btn-outline">View All Projects</a>
        </div>
    </div>
</section>
@endif

@if($featuredProducts->isNotEmpty())
<section class="max-w-7xl mx-auto px-5 py-24 va-reveal">
    <div class="text-center mb-16">
        <p class="va-label mb-3">Shop</p>
        <h2 class="font-serif text-4xl text-brand-900">Featured Products</h2>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-12 va-reveal-stagger">
        @foreach($featuredProducts as $i => $product)
        <a href="{{ route('shop.show', $product->slug) }}" class="va-card group" style="--stagger:{{ $i }}">
            <div class="aspect-[3/4] bg-brand-100 overflow-hidden mb-5 rounded-lg">
                @if($product->imageUrl())
                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                @endif
            </div>
            <p class="text-[10px] uppercase tracking-[0.2em] text-brand-400 mb-1">{{ $product->category?->name ?? 'Product' }}</p>
            <h3 class="font-serif text-xl text-brand-900 group-hover:text-brand-500 transition">{{ $product->name }}</h3>
            <p class="text-brand-500 mt-1">{{ $product->formattedPrice() }}</p>
        </a>
        @endforeach
    </div>
    <div class="text-center mt-14">
        <a href="{{ route('shop.index') }}" class="va-btn-outline">View All Products</a>
    </div>
</section>
@endif

@if(isset($latestPosts) && $latestPosts->isNotEmpty())
<section class="bg-brand-100 py-24 va-reveal">
    <div class="max-w-7xl mx-auto px-5">
        <div class="text-center mb-16">
            <p class="va-label mb-3">Insights</p>
            <h2 class="font-serif text-4xl text-brand-900">From the Blog</h2>
        </div>
        <div class="grid md:grid-cols-3 gap-8 va-reveal-stagger">
            @foreach($latestPosts as $i => $post)
            <a href="{{ route('blog.show', $post->slug) }}" class="va-card group block bg-brand-50 p-6 border border-brand-200 rounded-lg" style="--stagger:{{ $i }}">
                <time class="text-[10px] uppercase tracking-[0.2em] text-brand-400">{{ $post->published_at?->format('M d, Y') }}</time>
                <h3 class="font-serif text-xl text-brand-900 mt-2 group-hover:text-brand-500 transition">{{ $post->title }}</h3>
                <p class="text-sm text-brand-500 mt-3">{{ Str::limit($post->excerpt, 100) }}</p>
            </a>
            @endforeach
        </div>
        <div class="text-center mt-12">
            <a href="{{ route('blog.index') }}" class="va-btn-outline">Read All Articles</a>
        </div>
    </div>
</section>
@endif

<section class="relative py-32 px-5 text-center text-white va-reveal"
    style="background: linear-gradient(rgba(45,36,25,0.7), rgba(45,36,25,0.7)), url('https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=1600&q=80') center/cover;">
    <p class="va-label text-brand-200 mb-4">Get Started</p>
    <h2 class="font-serif text-4xl md:text-6xl mb-6">Start Your <span class="va-text-accent">Project</span></h2>
    <p class="text-brand-100 max-w-lg mx-auto mb-10 leading-relaxed font-light">Use our price calculator on any service page, or contact us for bespoke fabrication and furniture enquiries.</p>
    <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <a href="{{ route('services.index') }}" class="va-btn-primary">Explore Services</a>
        <a href="{{ route('contact.index') }}" class="va-btn-outline border-white text-white hover:bg-white hover:text-brand-900">Contact Us</a>
    </div>
</section>

@endsection

@push('scripts')
<script src="{{ asset('js/motion.js') }}"></script>
@endpush
