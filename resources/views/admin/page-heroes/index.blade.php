@extends('layouts.admin')
@section('title', 'Page Heroes')
@section('content')
<h1 class="text-2xl font-semibold mb-2">Page Heroes</h1>
<p class="text-sm text-gray-600 mb-6">{{ \App\Support\ResponsiveHero::adminUploadIntro() }} Homepage slides are under <a href="{{ route('admin.settings.edit') }}" class="text-blue-600">Site Settings</a>. Railings and Corten are under <a href="{{ route('admin.independent-pages.index') }}" class="text-blue-600">Independent Pages</a>. Shop collections are under <a href="{{ route('admin.collection-pages.index') }}" class="text-blue-600">Collection Pages</a>.</p>

@php
    $groups = collect($pages)->groupBy('group');
@endphp

@foreach($groups as $group => $items)
<section class="mb-8">
    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-3">{{ $group }}</h2>
    <table class="w-full bg-white rounded-lg shadow text-sm">
        <thead class="border-b">
            <tr class="text-left">
                <th class="p-3">Page</th>
                <th class="p-3">Hero title</th>
                <th class="p-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $page)
            <tr class="border-b">
                <td class="p-3">{{ $page['label'] }}</td>
                <td class="p-3">{{ $page['title'] }}</td>
                <td class="p-3 text-right space-x-3">
                    <a href="{{ $page['preview_url'] }}" target="_blank" rel="noopener" class="text-gray-600">Preview ↗</a>
                    <a href="{{ route('admin.page-heroes.edit', $page['slug']) }}" class="text-blue-600">Edit hero</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</section>
@endforeach
@endsection
