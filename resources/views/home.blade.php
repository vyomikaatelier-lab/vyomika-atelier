@extends('layouts.app')



@section('title', 'VYOMIKA ATELIER — Architectural Metalwork & Interiors')



@section('content')



{{-- Cinematic hero --}}

<section class="va-luxe-hero">

    <div class="va-luxe-hero-media">

        <img src="https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=1920&q=85" alt="VYOMIKA ATELIER architectural interior">

    </div>

    <div class="va-luxe-hero-content">

        <p class="va-eyebrow text-white/60">Architectural Metal Atelier</p>

        <h1>Where metal<br>becomes <em>architecture.</em></h1>

        <p class="va-hero-lead">Partitions, façades, entrance systems, and bespoke fabrication — crafted with the precision of fine furniture and the permanence of steel.</p>

        <div class="va-luxe-hero-cta flex flex-wrap gap-4">

            <a href="{{ route('services.index') }}" class="va-btn-luxe va-btn-luxe--light"><span>Explore Services</span></a>

            <a href="{{ route('projects.index') }}" class="va-btn-luxe va-btn-luxe--light"><span>View Projects</span></a>

        </div>

    </div>

    <div class="va-luxe-hero-scroll">Scroll</div>

</section>



{{-- Trust strip --}}

<div class="va-trust-strip va-reveal">

    <div class="va-trust-strip-inner">

        <div class="va-trust-item">

            <p class="va-trust-num">15+</p>

            <p class="va-trust-label">Years Craft</p>

        </div>

        <div class="va-trust-item">

            <p class="va-trust-num">200+</p>

            <p class="va-trust-label">Installations</p>

        </div>

        <div class="va-trust-item">

            <p class="va-trust-num">6</p>

            <p class="va-trust-label">Core Disciplines</p>

        </div>

        <div class="va-trust-item">

            <p class="va-trust-num">Pan</p>

            <p class="va-trust-label">India Delivery</p>

        </div>

    </div>

</div>



{{-- Manifesto --}}

<section class="va-manifesto va-reveal">

    <div class="va-manifesto-inner">

        <div>

            <div class="va-rule mb-8"></div>

            <p class="va-eyebrow mb-4">Our Philosophy</p>

        </div>

        <blockquote class="va-manifesto-quote">

            We believe exceptional spaces are built on <em>precision, material honesty,</em> and an unwavering commitment to craft — from the first line drawn to the final installation.

        </blockquote>

    </div>

</section>

{{-- How it works --}}
<section id="how-it-works" class="py-24 bg-brand-100 va-reveal scroll-mt-24">
    <div class="va-section-head va-reveal">
        <div>
            <p class="va-eyebrow mb-3">Process</p>
            <h2 class="va-display text-4xl md:text-5xl">How It Works</h2>
        </div>
        <a href="{{ route('contact.index') }}" class="va-btn-luxe">Get Started</a>
    </div>
    <div class="va-how-grid va-reveal-stagger">
        <div class="va-how-step" style="--stagger:0">
            <p class="va-how-num">01</p>
            <h3 class="font-serif text-xl mb-2">Consult</h3>
            <p class="text-sm text-brand-400 font-light leading-relaxed">Tell us your space, dimensions, materials, and timeline. We assess scope and feasibility.</p>
        </div>
        <div class="va-how-step" style="--stagger:1">
            <p class="va-how-num">02</p>
            <h3 class="font-serif text-xl mb-2">Calculate</h3>
            <p class="text-sm text-brand-400 font-light leading-relaxed">Use our online sq ft calculator on service pages for an instant estimate, or request a detailed quote.</p>
        </div>
        <div class="va-how-step" style="--stagger:2">
            <p class="va-how-num">03</p>
            <h3 class="font-serif text-xl mb-2">Fabricate</h3>
            <p class="text-sm text-brand-400 font-light leading-relaxed">Our atelier engineers and fabricates your partitions, façades, doors, or bespoke metalwork.</p>
        </div>
        <div class="va-how-step" style="--stagger:3">
            <p class="va-how-num">04</p>
            <h3 class="font-serif text-xl mb-2">Deliver</h3>
            <p class="text-sm text-brand-400 font-light leading-relaxed">Professional delivery and installation — built to perform and endure for years.</p>
        </div>
    </div>
</section>

{{-- Information --}}
<section id="information" class="py-24 va-reveal scroll-mt-24">
    <div class="va-info-grid">
        <div>
            <div class="va-rule mb-8"></div>
            <p class="va-eyebrow mb-4">Information</p>
            <h2 class="va-display text-3xl md:text-4xl mb-6">The atelier at a glance.</h2>
            <p class="text-brand-400 font-light leading-relaxed mb-6">VYOMIKA ATELIER is a fabrication studio specialising in architectural metalwork — glass partitions, Corten steel façades, slim profile door systems, PVD entrance doors, bespoke furniture, and curated home decor.</p>
            <a href="{{ route('about') }}" class="va-service-link">Read our full story →</a>
        </div>
        <div class="va-info-list">
            <div class="va-info-item"><span>Services</span><span>Partitions · Façades · Doors · PVD · Furniture</span></div>
            <div class="va-info-item"><span>Shop</span><span>Coffee tables · Corner tables · Glass tables · Handles</span></div>
            <div class="va-info-item"><span>Calculator</span><span>Length × Height × ₹1,800 / sq ft</span></div>
            <div class="va-info-item"><span>Enquiries</span><span>hello@vyomikaatelier.com</span></div>
            <div class="va-info-item"><span>Website</span><span>vyomikaatelier.com</span></div>
            <div class="va-info-item"><span>Delivery</span><span>Pan-India · Custom fabrication</span></div>
        </div>
    </div>
</section>

@if(isset($featuredServices) && $featuredServices->isNotEmpty())

<section class="va-services-luxe">

    <div class="va-section-head va-reveal">

        <div>

            <p class="va-eyebrow mb-3">Capabilities</p>

            <h2>Services</h2>

        </div>

        <a href="{{ route('services.index') }}" class="va-btn-luxe">All Services</a>

    </div>



    @foreach($featuredServices->take(3) as $service)

    <article class="va-service-feature va-reveal">

        <a href="{{ route('services.show', $service->slug) }}">

            @if($service->image)

                <img src="{{ $service->image }}" alt="{{ $service->name }}">

            @endif

        </a>

        <div class="va-service-feature-info">

            <p class="va-eyebrow mb-4">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</p>

            <a href="{{ route('services.show', $service->slug) }}">

                <h3>{{ $service->name }}</h3>

            </a>

            <p class="text-brand-400 font-light leading-relaxed mt-3 max-w-md">{{ $service->summary }}</p>

            <a href="{{ route('services.show', $service->slug) }}" class="va-service-link">Discover →</a>

        </div>

    </article>

    @endforeach

</section>

@endif



@if(isset($featuredProjects) && $featuredProjects->isNotEmpty())

<section class="va-projects-luxe">

    <div class="va-section-head va-reveal">

        <div>

            <p class="va-eyebrow mb-3">Portfolio</p>

            <h2>Selected Work</h2>

        </div>

        <a href="{{ route('projects.index') }}" class="va-btn-luxe va-btn-luxe--light">All Projects</a>

    </div>



    <div class="va-project-mosaic va-reveal-stagger">

        @foreach($featuredProjects->take(3) as $i => $project)

        <a href="{{ route('projects.show', $project->slug) }}"

           class="va-project-tile {{ $i === 0 ? 'va-project-hero' : '' }}"

           style="--stagger:{{ $i }}">

            @if($project->image)

                <img src="{{ $project->image }}" alt="{{ $project->title }}">

            @endif

            <div class="va-project-tile-overlay">

                @if($project->location)

                    <p class="va-eyebrow text-white/50 mb-2">{{ $project->location }}</p>

                @endif

                <h3>{{ $project->title }}</h3>

            </div>

        </a>

        @endforeach

    </div>

</section>

@endif



@if($featuredProducts->isNotEmpty())

<section id="shop" class="py-24 bg-brand-50 scroll-mt-24">

    <div class="va-section-head va-reveal">

        <div>

            <p class="va-eyebrow mb-3">The Collection</p>

            <h2 class="va-display text-4xl md:text-5xl">Curated Pieces</h2>

        </div>

        <a href="{{ route('shop.index') }}" class="va-btn-luxe">Shop All</a>

    </div>

    <div class="va-product-grid va-reveal-stagger">

        @foreach($featuredProducts->take(6) as $i => $product)

        <a href="{{ route('shop.show', $product->slug) }}" class="va-product-card va-card group" style="--stagger:{{ $i }}">

            <div class="va-product-img">

                @if($product->imageUrl())

                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="w-full h-full object-cover">

                @endif

            </div>

            <p class="va-eyebrow text-brand-400 mb-1">{{ $product->category?->name ?? 'Piece' }}</p>

            <h3>{{ $product->name }}</h3>

            <p class="va-product-price">{{ $product->formattedPrice() }}</p>

        </a>

        @endforeach

    </div>

</section>

@endif



@if(isset($latestPosts) && $latestPosts->isNotEmpty())

<section id="blog" class="va-blog-luxe scroll-mt-24">

    <div class="va-section-head va-reveal">

        <div>

            <p class="va-eyebrow mb-3">Journal</p>

            <h2 class="va-display text-4xl md:text-5xl">Insights</h2>

        </div>

        <a href="{{ route('blog.index') }}" class="va-btn-luxe">Read All</a>

    </div>

    <div class="max-w-[90rem] mx-auto px-5 lg:px-10 grid md:grid-cols-3 gap-10 va-reveal-stagger">

        @foreach($latestPosts as $i => $post)

        <a href="{{ route('blog.show', $post->slug) }}" class="group block" style="--stagger:{{ $i }}">

            @if($post->image)

            <div class="aspect-[16/10] overflow-hidden mb-5 bg-brand-100">

                <img src="{{ $post->image }}" alt="" class="w-full h-full object-cover group-hover:scale-105 transition duration-700">

            </div>

            @endif

            <time class="va-eyebrow text-brand-400">{{ $post->published_at?->format('F Y') }}</time>

            <h3 class="font-serif text-2xl font-light mt-2 group-hover:text-brand-500 transition leading-snug">{{ $post->title }}</h3>

        </a>

        @endforeach

    </div>

</section>

@endif



<section class="va-cta-luxe va-reveal">

    <div class="va-cta-luxe-bg">

        <img src="https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=1920&q=85" alt="">

    </div>

    <div class="va-cta-luxe-content">

        <p class="va-eyebrow text-white/50">Begin</p>

        <h2>Commission your next space.</h2>

        <p class="text-white/65 font-light leading-relaxed mb-10 max-w-md mx-auto">From concept to installation — our team guides every detail of your architectural metal project.</p>

        <div class="flex flex-wrap gap-4 justify-center">

            <a href="{{ route('contact.index') }}" class="va-btn-luxe va-btn-luxe--light"><span>Enquire</span></a>

            <a href="{{ route('services.index') }}" class="va-btn-luxe va-btn-luxe--light"><span>View Services</span></a>

        </div>

    </div>

</section>



@endsection

