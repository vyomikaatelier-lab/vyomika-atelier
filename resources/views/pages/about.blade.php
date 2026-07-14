@extends('layouts.app')

@section('title', 'Our Story — VYOMIKA ATELIER')

@section('content')
<div class="va-page-hero">
    <p class="va-label mb-3">The Atelier</p>
    <h1 class="font-serif text-5xl text-brand-900">Our Story</h1>
</div>

<section class="max-w-7xl mx-auto px-5 py-20 grid md:grid-cols-2 gap-16 items-center">
    <img src="https://images.unsplash.com/photo-1558618666-fcd25c85f82e?w=800&q=80" alt="Atelier" class="w-full aspect-[4/5] object-cover">
    <div>
        <div class="va-rule mb-6"></div>
        <h2 class="font-serif text-4xl mb-6">Born from a love of craft</h2>
        <p class="text-brand-700 leading-relaxed mb-5">VYOMIKA ATELIER was founded on a simple belief: that clothing should feel as intentional as the moment you wear it for. Every piece — whether from our ready-to-wear collection or a bespoke commission — is shaped by skilled hands and thoughtful design.</p>
        <p class="text-brand-700 leading-relaxed mb-5">We work with natural fabrics, traditional techniques, and contemporary silhouettes to create garments that honour both heritage and modern elegance.</p>
        <p class="text-brand-500 leading-relaxed italic font-serif text-xl">"Fashion fades, but style is eternal."</p>
    </div>
</section>

<section class="bg-brand-900 text-white py-20 px-5 text-center">
    <p class="va-label text-brand-400 mb-4">What We Offer</p>
    <div class="max-w-4xl mx-auto grid sm:grid-cols-3 gap-10 mt-10">
        <div>
            <h3 class="font-serif text-2xl mb-3">Ready-to-Wear</h3>
            <p class="text-brand-400 text-sm leading-relaxed">Curated collections of artisanal pieces, available to shop online.</p>
        </div>
        <div>
            <h3 class="font-serif text-2xl mb-3">Bespoke</h3>
            <p class="text-brand-400 text-sm leading-relaxed">Commission a one-of-a-kind garment tailored to your vision and measurements.</p>
        </div>
        <div>
            <h3 class="font-serif text-2xl mb-3">Occasion Wear</h3>
            <p class="text-brand-400 text-sm leading-relaxed">Weddings, celebrations, and moments that deserve something extraordinary.</p>
        </div>
    </div>
</section>
@endsection
