@extends('layouts.admin')

@section('title', 'Products')

@section('content')
@php
    $listParams = fn (array $extra = []) => array_filter(array_merge(
        request()->only(['section', 'category_id', 'filter']),
        $extra
    ), fn ($value) => filled($value));
@endphp

<div class="flex flex-wrap justify-between items-start gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-semibold">Products</h1>
        @if($unclassifiedCount > 0)
        <p class="text-sm text-amber-700 mt-1">{{ $unclassifiedCount }} product(s) need classification.</p>
        @endif
        @if($categoryFilter)
        <p class="text-sm text-gray-600 mt-1">Showing <strong>{{ $products->total() }}</strong> in <strong>{{ $categoryFilter->name }}</strong></p>
        @elseif($activeSection)
        <p class="text-sm text-gray-600 mt-1">Showing <strong>{{ $products->total() }}</strong> in <strong>{{ $sectionLabels[$activeSection] ?? ucfirst($activeSection) }}</strong></p>
        @else
        <p class="text-sm text-gray-600 mt-1"><strong>{{ $totalProductCount }}</strong> products total</p>
        @endif
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.products.index', $listParams(['filter' => request('filter') === 'unclassified' ? null : 'unclassified'])) }}"
           class="px-4 py-2 rounded text-sm border {{ request('filter') === 'unclassified' ? 'bg-amber-100 border-amber-300' : 'bg-white' }}">
            {{ request('filter') === 'unclassified' ? 'Show all' : 'Unclassified' }}
        </a>
        <a href="{{ route('admin.products.create') }}" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Add Product</a>
    </div>
</div>

<div class="bg-white rounded-lg shadow p-4 mb-4 space-y-4">
    <div>
        <p class="text-xs uppercase tracking-wide text-gray-500 mb-2">Section</p>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.products.index', $listParams(['section' => null])) }}"
               class="px-3 py-1.5 rounded-full text-sm border {{ ! $activeSection ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700' }}">
                All sections
            </a>
            @foreach($sectionLabels as $value => $label)
            <a href="{{ route('admin.products.index', $listParams(['section' => $value, 'category_id' => null])) }}"
               class="px-3 py-1.5 rounded-full text-sm border {{ $activeSection === $value ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700' }}">
                {{ $label }}
            </a>
            @endforeach
        </div>
    </div>

    <div>
        <p class="text-xs uppercase tracking-wide text-gray-500 mb-2">Parent category</p>
        <form method="GET" action="{{ route('admin.products.index') }}" class="max-w-md">
            @foreach(request()->only(['section', 'filter']) as $key => $value)
                @if(filled($value))
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <select name="category_id" onchange="this.form.submit()" class="w-full border border-gray-300 rounded px-3 py-2 text-sm bg-white">
                <option value="">All categories ({{ $totalProductCount }})</option>
                @foreach($categorySectionOrder as $sectionKey)
                    @php
                        $sectionCategories = $categories->get($sectionKey, collect());
                        $label = $sectionLabels[$sectionKey] ?? 'Other';
                    @endphp
                    @if($sectionCategories->isNotEmpty())
                    <optgroup label="{{ $label }}">
                        @foreach($sectionCategories as $category)
                        <option value="{{ $category->id }}" @selected($categoryFilter?->id === $category->id)>
                            {{ $category->name }} ({{ $category->products_count }})
                        </option>
                        @endforeach
                    </optgroup>
                    @endif
                @endforeach
            </select>
        </form>
    </div>
</div>

<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b">
        <tr class="text-left">
            <th class="p-3">Name</th>
            <th class="p-3">Section</th>
            <th class="p-3">Parent</th>
            <th class="p-3">Purchase</th>
            <th class="p-3">Pricing</th>
            <th class="p-3">Price</th>
            <th class="p-3">Stock</th>
            <th class="p-3">Status</th>
            <th class="p-3"></th>
        </tr>
    </thead>
    <tbody>
        @forelse($products as $product)
        <tr class="border-b {{ ! $product->isClassified() ? 'bg-amber-50' : '' }}">
            <td class="p-3">{{ $product->name }}</td>
            <td class="p-3">{{ ucfirst($product->resolvedSection() ?? 'Unclassified') }}</td>
            <td class="p-3">{{ $product->category?->name ?? '—' }}</td>
            <td class="p-3">{{ str_replace('_', ' ', $product->resolvedPurchaseMode()) }}</td>
            <td class="p-3">{{ str_replace('_', ' ', $product->resolvedPricingType()) }}</td>
            <td class="p-3">₹{{ number_format($product->price, 0) }}</td>
            <td class="p-3">{{ $product->stock }}</td>
            <td class="p-3">{{ $product->is_active ? 'Active' : 'Hidden' }}</td>
            <td class="p-3 whitespace-nowrap">
                <a href="{{ route('shop.show', $product->slug) }}" class="text-blue-600" target="_blank" rel="noopener">View</a>
                <a href="{{ route('admin.products.edit', $product) }}" class="text-blue-600">Edit</a>
                <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="text-red-600">Delete</button></form>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="9" class="p-6 text-center text-gray-500">No products match this filter.</td>
        </tr>
        @endforelse
    </tbody>
</table>
<div class="mt-4">{{ $products->links() }}</div>
@endsection
