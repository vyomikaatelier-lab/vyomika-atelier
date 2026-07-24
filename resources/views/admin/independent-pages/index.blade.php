@extends('layouts.admin')
@section('title', 'Independent Pages')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Independent Pages</h1>
<p class="text-sm text-gray-600 mb-4">Edit every section of the Railings and Corten Steel public pages (hero, galleries, FAQ, CTAs). Separate from product categories.</p>
<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b"><tr class="text-left"><th class="p-3">Page</th><th class="p-3">Hero title</th><th class="p-3"></th></tr></thead>
    <tbody>
        @foreach($pages as $page)
        <tr class="border-b">
            <td class="p-3">{{ $page['label'] }}</td>
            <td class="p-3">{{ $page['title'] }}</td>
            <td class="p-3 space-x-3">
                <a href="{{ route('admin.independent-pages.edit', $page['slug']) }}" class="text-blue-600">Edit</a>
                <a href="{{ $page['url'] }}" target="_blank" rel="noopener" class="text-gray-600">View ↗</a>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endsection
