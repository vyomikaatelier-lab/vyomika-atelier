@extends('layouts.admin')
@section('title', isset($project) ? 'Edit Project' : 'Add Project')
@section('content')
<h1 class="text-2xl font-semibold mb-6">{{ isset($project) ? 'Edit' : 'Add' }} Project</h1>
<form method="POST" action="{{ isset($project) ? route('admin.projects.update', $project) : route('admin.projects.store') }}" enctype="multipart/form-data" class="bg-white p-6 rounded shadow space-y-4 max-w-3xl">
    @csrf @if(isset($project)) @method('PUT') @endif
    <div class="grid md:grid-cols-2 gap-4">
        <div><label class="block text-sm mb-1">Title</label><input name="title" value="{{ old('title', $project->title ?? '') }}" required class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Slug</label><input name="slug" value="{{ old('slug', $project->slug ?? '') }}" placeholder="Auto from title if blank" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Category</label><select name="category" class="w-full border px-3 py-2 rounded"><option value="">—</option>@foreach(\App\Models\Project::categoryLabels() as $key => $label)<option value="{{ $key }}" @selected(old('category', $project->category ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div><label class="block text-sm mb-1">Location</label><input name="location" value="{{ old('location', $project->location ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Year</label><input type="number" name="year" value="{{ old('year', $project->year ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Client</label><input name="client" value="{{ old('client', $project->client ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Completed at</label><input type="date" name="completed_at" value="{{ old('completed_at', optional($project->completed_at ?? null)->format('Y-m-d')) }}" class="w-full border px-3 py-2 rounded"></div>
    </div>
    <div><label class="block text-sm mb-1">Short context</label><textarea name="summary" rows="2" class="w-full border px-3 py-2 rounded">{{ old('summary', $project->summary ?? '') }}</textarea></div>
    <div><label class="block text-sm mb-1">Full description</label><textarea name="content" rows="6" class="w-full border px-3 py-2 rounded">{{ old('content', $project->content ?? '') }}</textarea></div>
    <div><label class="block text-sm mb-1">Design details</label><textarea name="design_details" rows="3" class="w-full border px-3 py-2 rounded">{{ old('design_details', $project->design_details ?? '') }}</textarea></div>
    <div><label class="block text-sm mb-1">Scope</label><textarea name="scope" rows="2" class="w-full border px-3 py-2 rounded">{{ old('scope', $project->scope ?? '') }}</textarea></div>
    <div><label class="block text-sm mb-1">Challenges</label><textarea name="challenges" rows="2" class="w-full border px-3 py-2 rounded">{{ old('challenges', $project->challenges ?? '') }}</textarea></div>
    <div><label class="block text-sm mb-1">Materials (one per line)</label><textarea name="materials_list" rows="3" class="w-full border px-3 py-2 rounded">{{ old('materials_list', isset($project) && is_array($project->materials) ? implode("\n", $project->materials) : '') }}</textarea></div>
    <div><label class="block text-sm mb-1">Finishes (one per line)</label><textarea name="finishes_list" rows="3" class="w-full border px-3 py-2 rounded">{{ old('finishes_list', isset($project) && is_array($project->finishes) ? implode("\n", $project->finishes) : '') }}</textarea></div>
    <div class="grid md:grid-cols-2 gap-4 border rounded p-4 bg-gray-50">
        <div><label class="block text-sm mb-1">Testimonial quote</label><textarea name="testimonial_quote" rows="3" class="w-full border px-3 py-2 rounded">{{ old('testimonial_quote', $project->testimonial_quote ?? '') }}</textarea></div>
        <div class="space-y-3">
            <div><label class="block text-sm mb-1">Testimonial author</label><input name="testimonial_author" value="{{ old('testimonial_author', $project->testimonial_author ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
            <div><label class="block text-sm mb-1">Testimonial role</label><input name="testimonial_role" value="{{ old('testimonial_role', $project->testimonial_role ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
        </div>
    </div>
    <div class="space-y-3 border rounded p-4 bg-gray-50">
        <p class="text-sm font-medium">Cover image</p>
        @if(isset($project) && $project->imageUrl())
            <img src="{{ $project->imageUrl() }}" alt="" class="w-40 h-28 object-cover rounded border">
        @endif
        <div><label class="block text-sm mb-1">Cover image URL</label><input name="image" value="{{ old('image', $project->image ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Upload cover</label><input type="file" name="image_file" accept="image/*"></div>
    </div>
    @include('admin.partials.gallery-upload-fields', ['gallery' => isset($project) ? $project->gallery : null, 'directory' => 'projects'])
    <div class="grid md:grid-cols-2 gap-4">
        <div><label class="block text-sm mb-1">SEO title</label><input name="meta_title" value="{{ old('meta_title', $project->meta_title ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Display order</label><input type="number" name="display_order" min="0" value="{{ old('display_order', $project->display_order ?? 0) }}" class="w-full border px-3 py-2 rounded"></div>
    </div>
    <div><label class="block text-sm mb-1">Meta description</label><input name="meta_description" value="{{ old('meta_description', $project->meta_description ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
    <div class="flex gap-4 text-sm">
        <label class="flex items-center gap-2"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $project->is_featured ?? false))> Featured</label>
        <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $project->is_active ?? true))> Published</label>
    </div>
    <button class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save</button>
</form>
@endsection
