@extends('layouts.admin')
@section('title', 'URL Redirects')
@section('content')
<h1 class="text-2xl font-semibold mb-4">URL Redirects</h1>
<form method="POST" action="{{ route('admin.redirects.store') }}" class="bg-white p-4 rounded shadow mb-6 max-w-3xl space-y-3">
    @csrf
    <div class="grid md:grid-cols-2 gap-3">
        <input name="from_path" placeholder="/old-path" required class="border px-3 py-2 rounded">
        <input name="to_url" placeholder="/new-path or https://…" required class="border px-3 py-2 rounded">
    </div>
    <div class="flex gap-3 items-center">
        <select name="status_code" class="border px-3 py-2 rounded">
            <option value="301">301</option>
            <option value="302">302</option>
        </select>
        <label class="text-sm"><input type="checkbox" name="is_active" value="1" checked> Active</label>
        <button class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Add redirect</button>
    </div>
</form>
<table class="w-full bg-white rounded shadow text-sm">
    <thead class="border-b"><tr class="text-left"><th class="p-3">From</th><th class="p-3">To</th><th class="p-3">Code</th><th class="p-3"></th></tr></thead>
    <tbody>
        @forelse($redirects as $redirect)
        <tr class="border-b">
            <td class="p-3 font-mono text-xs">{{ $redirect->from_path }}</td>
            <td class="p-3 font-mono text-xs">{{ $redirect->to_url }}</td>
            <td class="p-3">{{ $redirect->status_code }} @unless($redirect->is_active)<span class="text-red-600">off</span>@endunless</td>
            <td class="p-3">
                <form method="POST" action="{{ route('admin.redirects.destroy', $redirect) }}" onsubmit="return confirm('Remove redirect?')">
                    @csrf @method('DELETE')
                    <button class="text-red-600">Remove</button>
                </form>
            </td>
        </tr>
        @empty
        <tr><td class="p-3 text-gray-500" colspan="4">No custom redirects yet. Route-level 301s still apply.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="mt-4">{{ $redirects->links() }}</div>
@endsection
