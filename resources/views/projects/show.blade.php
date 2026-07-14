@extends('layouts.app')

@section('title', $project->title . ' — Projects — VYOMIKA ATELIER')

@section('content')
<div class="relative h-[50vh] min-h-[320px] bg-brand-900">
    @if($project->image)
        <img src="{{ $project->image }}" alt="{{ $project->title }}" class="w-full h-full object-cover opacity-85">
    @endif
    <div class="absolute inset-0 bg-gradient-to-t from-brand-900/80 to-transparent"></div>
    <div class="absolute bottom-0 left-0 right-0 max-w-7xl mx-auto px-5 pb-12">
        @if($project->location)
            <p class="va-label text-brand-200 mb-2">{{ $project->location }}</p>
        @endif
        <h1 class="font-serif text-4xl md:text-5xl text-white">{{ $project->title }}</h1>
    </div>
</div>

<div class="max-w-4xl mx-auto px-5 py-16">
    <p class="text-brand-600 text-lg leading-relaxed mb-10">{{ $project->summary }}</p>
    @if($project->content)
        <div class="prose-brand text-brand-700 leading-relaxed mb-12">{!! $project->content !!}</div>
    @endif

    @if($project->gallery && count($project->gallery) > 0)
        <p class="va-label mb-6">Gallery</p>
        <div class="grid sm:grid-cols-2 gap-4">
            @foreach($project->gallery as $image)
                <img src="{{ $image }}" alt="{{ $project->title }}" class="w-full aspect-[4/3] object-cover">
            @endforeach
        </div>
    @endif

    <div class="mt-12 text-center">
        <a href="{{ route('projects.index') }}" class="va-btn-outline">← All Projects</a>
    </div>
</div>
@endsection
