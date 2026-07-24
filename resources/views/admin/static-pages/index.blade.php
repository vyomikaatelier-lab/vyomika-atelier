@extends('layouts.admin')
@section('title', 'Static Pages SEO')
@section('content')
<h1 class="text-2xl font-semibold mb-4">Static Pages SEO</h1>
<p class="text-sm text-gray-600 mb-4">Edit SEO and intro copy for core pages (home, shop, studio, about, professionals, projects, blog, contact).</p>
<table class="w-full bg-white rounded shadow text-sm">
    <thead class="border-b"><tr class="text-left"><th class="p-3">Page</th><th class="p-3">SEO title</th><th class="p-3"></th></tr></thead>
    <tbody>
        @foreach($pages as $page)
        <tr class="border-b">
            <td class="p-3">{{ $page['label'] }}</td>
            <td class="p-3">{{ $page['title'] }}</td>
            <td class="p-3"><a href="{{ route('admin.static-pages.edit', $page['slug']) }}" class="text-blue-600">Edit</a></td>
        </tr>
        @endforeach
    </tbody>
</table>
@endsection
