@extends('layouts.admin')
@section('title', 'Legal Pages')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Legal Pages</h1>
@if($pages->isEmpty())<p class="text-gray-500 bg-white p-6 rounded shadow">No legal pages. Run <code>php artisan db:seed --class=CmsContentSeeder</code>.</p>@else
<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b"><tr class="text-left"><th class="p-3">Slug</th><th class="p-3">Title</th><th class="p-3">Last updated</th><th class="p-3"></th></tr></thead>
    <tbody>@foreach($pages as $page)<tr class="border-b">
        <td class="p-3">{{ $page->slug }}</td><td class="p-3">{{ $page->title }}</td><td class="p-3">{{ optional($page->content_updated_at)->format('d M Y') }}</td>
        <td class="p-3"><a href="{{ route('admin.legal.edit', $page) }}" class="text-blue-600">Edit</a></td>
    </tr>@endforeach</tbody>
</table>@endif
@endsection
