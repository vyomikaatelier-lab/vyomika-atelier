@extends('layouts.admin')
@section('title', 'Collection Pages')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Collection Pages</h1>
<p class="text-sm text-gray-600 mb-4">Edit hero, intro, and SEO overrides for shop collection landing pages.</p>
@if($pages === [])
    <p class="text-gray-500 bg-white p-6 rounded shadow">No collection pages configured.</p>
@else
<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b"><tr class="text-left"><th class="p-3">Slug</th><th class="p-3">Title</th><th class="p-3"></th></tr></thead>
    <tbody>
        @foreach($pages as $page)
        <tr class="border-b">
            <td class="p-3">{{ $page['slug'] }}</td>
            <td class="p-3">{{ $page['title'] }}</td>
            <td class="p-3"><a href="{{ route('admin.collection-pages.edit', $page['slug']) }}" class="text-blue-600">Edit</a></td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif
@endsection
