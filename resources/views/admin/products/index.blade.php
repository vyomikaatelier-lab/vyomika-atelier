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
        <div class="flex flex-wrap gap-2 mb-3">
            <a href="{{ route('admin.products.index', $listParams(['category_id' => null])) }}"
               class="px-3 py-1.5 rounded-full text-sm border {{ ! $categoryFilter ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700' }}">
                All categories ({{ $totalProductCount }})
            </a>
        </div>

        @foreach($sectionOrder as $sectionKey)
            @php
                $sectionCategories = $categories->get($sectionKey, collect());
                $label = $sectionLabels[$sectionKey] ?? 'Other';
            @endphp
            @if($sectionCategories->isNotEmpty())
            <div class="mb-3">
                <p class="text-xs font-medium text-gray-500 mb-1.5">{{ $label }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($sectionCategories as $category)
                    <a href="{{ route('admin.products.index', $listParams(['category_id' => $category->id, 'section' => $sectionKey !== 'other' ? $sectionKey : null])) }}"
                       class="px-3 py-1.5 rounded-full text-sm border {{ ($categoryFilter?->id === $category->id) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700' }}">
                        {{ $category->name }}
                        <span class="opacity-75">({{ $category->products_count }})</span>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        @endforeach
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
