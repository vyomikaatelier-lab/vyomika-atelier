@extends('layouts.admin')

@section('title', 'Products')

@section('content')
@php
    $listParams = fn (array $extra = []) => array_filter(array_merge(
        request()->only(['section', 'category_id', 'filter', 'q']),
        $extra
    ), fn ($value) => filled($value));
@endphp

<div class="flex flex-wrap justify-between items-start gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-semibold">Products</h1>
        @if($categoryFilter)
        <p class="text-sm text-gray-600 mt-1">Showing <strong>{{ $products->count() }}</strong> in <strong>{{ $categoryFilter->name }}</strong></p>
        @elseif($activeSection)
        <p class="text-sm text-gray-600 mt-1">Showing <strong>{{ $products->count() }}</strong> in <strong>{{ $sectionLabels[$activeSection] ?? ucfirst($activeSection) }}</strong></p>
        @else
        <p class="text-sm text-gray-600 mt-1"><strong>{{ $totalProductCount }}</strong> products total</p>
        @endif
        @if($products->isNotEmpty())
        <p class="text-sm text-gray-500 mt-1">Drag rows to set display order (top = first on the website).</p>
        @endif
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.products.create', $listParams()) }}" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Add Product</a>
    </div>
</div>

<div class="bg-white rounded-lg shadow p-4 mb-4 space-y-4">
    <form method="GET" action="{{ route('admin.products.index') }}" class="flex flex-wrap gap-2 text-sm max-w-xl">
        @foreach(request()->only(['section', 'category_id', 'filter']) as $key => $value)
            @if(filled($value))
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, slug, or SKU…" class="flex-1 min-w-[12rem] border border-gray-300 rounded px-3 py-2">
        <button type="submit" class="border px-3 py-2 rounded">Search</button>
        @if(request()->filled('q'))
        <a href="{{ route('admin.products.index', $listParams(['q' => null])) }}" class="border px-3 py-2 rounded text-gray-600">Clear</a>
        @endif
    </form>

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
            @foreach(request()->only(['section', 'filter', 'q']) as $key => $value)
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

@if(session('success'))
<div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4 text-sm">{{ session('success') }}</div>
@endif

@if($products->isNotEmpty() && \Illuminate\Support\Facades\Route::has('admin.products.bulk'))
<form method="POST" action="{{ route('admin.products.bulk') }}" id="product-bulk-form" class="mb-3 flex flex-wrap items-center gap-2 text-sm bg-white rounded-lg shadow px-4 py-3">
    @csrf
    @foreach($listParams() as $key => $value)
    <input type="hidden" name="_return_{{ $key }}" value="{{ $value }}">
    @endforeach
    <label class="sr-only" for="product-bulk-action">Bulk action</label>
    <select id="product-bulk-action" name="action" required class="border border-gray-300 rounded px-3 py-2 bg-white min-w-[12rem]">
        <option value="">Bulk actions…</option>
        <option value="activate">Show on storefront</option>
        <option value="deactivate">Hide from storefront</option>
        <option value="show_gallery">Show in gallery grids</option>
        <option value="hide_gallery">Hide from gallery grids</option>
        <option value="hide_when_oos">Hide when out of stock</option>
        <option value="show_when_oos">Keep visible when out of stock</option>
        <option value="delete">Delete selected</option>
    </select>
    <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded">Apply</button>
    <span id="product-bulk-count" class="text-gray-500">0 selected</span>
</form>
@endif

<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b">
        <tr class="text-left">
            @if($products->isNotEmpty() && \Illuminate\Support\Facades\Route::has('admin.products.bulk'))
            <th class="p-3 w-10"><input type="checkbox" id="product-select-all" aria-label="Select all products on this page"></th>
            @endif
            <th class="p-3 w-10">#</th>
            <th class="p-3 w-10"></th>
            <th class="p-3">Product</th>
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
    <tbody id="product-sortable-list">
        @forelse($products as $product)
        <tr class="border-b" data-product-id="{{ $product->id }}">
            @if(\Illuminate\Support\Facades\Route::has('admin.products.bulk'))
            <td class="p-3"><input type="checkbox" form="product-bulk-form" name="ids[]" value="{{ $product->id }}" class="product-bulk-check" aria-label="Select {{ $product->name }}"></td>
            @endif
            <td class="p-3 text-gray-500 tabular-nums">{{ $loop->iteration }}</td>
            <td class="p-3 text-gray-400 cursor-grab active:cursor-grabbing select-none product-drag-handle" title="Drag to reorder">⋮⋮</td>
            <td class="p-3">
                <div class="flex items-center gap-3 min-w-0">
                    @if($product->imageUrl())
                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}"
                         class="w-14 h-14 object-cover rounded border bg-gray-50 shrink-0"
                         loading="lazy" width="56" height="56">
                    @else
                    <span class="inline-flex w-14 h-14 items-center justify-center rounded border bg-gray-100 text-xs text-gray-400 shrink-0">No image</span>
                    @endif
                    <span class="font-medium truncate">{{ $product->name }}</span>
                </div>
            </td>
            <td class="p-3">{{ ucfirst($product->resolvedSection() ?? 'Unclassified') }}</td>
            <td class="p-3">{{ $product->category?->name ?? '—' }}</td>
            <td class="p-3">{{ str_replace('_', ' ', $product->resolvedPurchaseMode()) }}</td>
            <td class="p-3">{{ str_replace('_', ' ', $product->resolvedPricingType()) }}</td>
            <td class="p-3">₹{{ number_format($product->price, 0) }}</td>
            <td class="p-3">{{ $product->stock }}</td>
            <td class="p-3">{{ $product->is_active ? 'Active' : 'Hidden' }}</td>
            <td class="p-3 whitespace-nowrap">
                <a href="{{ route('shop.show', $product->slug) }}" class="text-blue-600" target="_blank" rel="noopener">View</a>
                <a href="{{ route('admin.products.edit', array_merge(['product' => $product], $listParams())) }}" class="text-blue-600">Edit</a>
                <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="inline" onsubmit="return confirm('Delete?')">
                    @csrf
                    @method('DELETE')
                    @foreach($listParams() as $key => $value)
                    <input type="hidden" name="_return_{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <button class="text-red-600">Delete</button>
                </form>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="12" class="p-6 text-center text-gray-500">No products match this filter.</td>
        </tr>
        @endforelse
    </tbody>
</table>

@if($products->isNotEmpty())
<p id="product-reorder-status" class="mt-2 text-sm text-gray-500" aria-live="polite"></p>
<script>
(function () {
    var bulkForm = document.getElementById('product-bulk-form');
    var selectAll = document.getElementById('product-select-all');
    var countEl = document.getElementById('product-bulk-count');

    function updateBulkCount() {
        if (!countEl) return;
        var n = document.querySelectorAll('.product-bulk-check:checked').length;
        countEl.textContent = n + ' selected';
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.product-bulk-check').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
            updateBulkCount();
        });
    }

    document.querySelectorAll('.product-bulk-check').forEach(function (cb) {
        cb.addEventListener('change', updateBulkCount);
    });

    if (bulkForm) {
        bulkForm.addEventListener('submit', function (e) {
            var checked = document.querySelectorAll('.product-bulk-check:checked').length;
            if (checked === 0) {
                e.preventDefault();
                alert('Select at least one product.');
                return;
            }
            var action = bulkForm.querySelector('[name=action]').value;
            if (action === 'delete' && ! confirm('Delete ' + checked + ' product(s)? This cannot be undone.')) {
                e.preventDefault();
            }
        });
    }
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    var list = document.getElementById('product-sortable-list');
    var status = document.getElementById('product-reorder-status');
    if (!list || typeof Sortable === 'undefined') return;

    var reorderUrl = @json(route('admin.products.reorder'));
    var filterPayload = @json($listParams());

    Sortable.create(list, {
        handle: '.product-drag-handle',
        animation: 150,
        onEnd: function () {
            var order = Array.from(list.querySelectorAll('[data-product-id]')).map(function (row) {
                return parseInt(row.getAttribute('data-product-id'), 10);
            });

            status.textContent = 'Saving order…';

            fetch(reorderUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': @json(csrf_token()),
                },
                body: JSON.stringify(Object.assign({}, filterPayload, { order: order })),
            })
            .then(function (response) {
                if (!response.ok) throw new Error('Save failed');
                status.textContent = 'Order saved.';
            })
            .catch(function () {
                status.textContent = 'Could not save order. Refresh and try again.';
            });
        },
    });
})();
</script>
@endif
@endsection
