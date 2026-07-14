@extends('layouts.app')

@section('title', 'Our Story — VYOMIKA ATELIER')

@section('content')
<div class="va-page-hero">
    <p class="va-label mb-3">About Us</p>
    <h1 class="font-serif text-5xl text-brand-900">Our Story</h1>
</div>

<section class="max-w-7xl mx-auto px-5 py-20 grid md:grid-cols-2 gap-16 items-center">
    <img src="https://images.unsplash.com/photo-1497366216548-37526070297c?w=800&q=80" alt="VYOMIKA ATELIER workspace" class="w-full aspect-[4/5] object-cover">
    <div>
        <div class="va-rule mb-6"></div>
        <h2 class="font-serif text-4xl mb-6">Metal fabrication meets design</h2>
        <p class="text-brand-700 leading-relaxed mb-5">VYOMIKA ATELIER is a fabrication studio specialising in architectural metalwork — partitions, Corten steel façades, slim profile door systems, bespoke metal furniture, and PVD-coated entrance doors.</p>
        <p class="text-brand-700 leading-relaxed mb-5">We also curate home decor furniture including coffee tables, corner tables, and glass tables. Every project is engineered for precision, durability, and lasting aesthetic impact.</p>
        <p class="text-brand-500 leading-relaxed italic font-serif text-xl">"Spaces deserve craftsmanship that endures."</p>
    </div>
</section>

<section class="bg-brand-900 text-white py-20 px-5 text-center">
    <p class="va-label text-brand-400 mb-4">What We Offer</p>
    <div class="max-w-5xl mx-auto grid sm:grid-cols-2 lg:grid-cols-3 gap-10 mt-10">
        <div>
            <h3 class="font-serif text-2xl mb-3">Partitions</h3>
            <p class="text-brand-400 text-sm leading-relaxed">Frameless glass, aluminium frame, sliding, and folding partition systems.</p>
        </div>
        <div>
            <h3 class="font-serif text-2xl mb-3">Façades &amp; Doors</h3>
            <p class="text-brand-400 text-sm leading-relaxed">Corten steel façades, slim profile doors, and main entrance PVD doors.</p>
        </div>
        <div>
            <h3 class="font-serif text-2xl mb-3">Furniture &amp; PVD</h3>
            <p class="text-brand-400 text-sm leading-relaxed">Bespoke metal furniture, rack systems, door handles, and home decor tables.</p>
        </div>
    </div>
</section>
@endsection
