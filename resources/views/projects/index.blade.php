@extends('layouts.app')

@section('title', 'Projects — VYOMIKA ATELIER')

@section('content')
<div class="va-page-hero">
    <p class="va-label mb-3">Our Work</p>
    <h1 class="font-serif text-5xl text-brand-900">Projects</h1>
    <p class="text-brand-500 mt-4 max-w-lg mx-auto">A selection of completed installations across residential and commercial spaces.</p>
</div>

<div class="max-w-7xl mx-auto px-5 py-16">
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
        @foreach($projects as $project)
        <a href="{{ route('projects.show', $project->slug) }}" class="va-card group block">
            <div class="aspect-[4/3] bg-brand-100 overflow-hidden mb-4">
                @if($project->image)
                    <img src="{{ $project->image }}" alt="{{ $project->title }}" class="w-full h-full object-cover">
                @endif
            </div>
            @if($project->location)
                <p class="text-[10px] uppercase tracking-[0.2em] text-brand-400 mb-1">{{ $project->location }}</p>
            @endif
            <h2 class="font-serif text-xl text-brand-900 group-hover:text-brand-500 transition">{{ $project->title }}</h2>
            <p class="text-sm text-brand-500 mt-2">{{ $project->summary }}</p>
        </a>
        @endforeach
    </div>
    <div class="mt-14">{{ $projects->links() }}</div>
</div>
@endsection
