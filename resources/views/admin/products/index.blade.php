@extends('layouts.admin')

@section('title', 'Products')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">Products</h1>
    <a href="{{ route('admin.products.create') }}" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Add Product</a>
</div>

<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b"><tr class="text-left"><th class="p-3">Name</th><th class="p-3">Section</th><th class="p-3">Price</th><th class="p-3">Stock</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead>
    <tbody>
        @foreach($products as $product)
        <tr class="border-b">
            <td class="p-3">{{ $product->name }}</td>
            <td class="p-3">{{ ucfirst($product->resolvedSection() ?? '—') }}</td>
            <td class="p-3">₹{{ number_format($product->price, 0) }}</td>
            <td class="p-3">{{ $product->stock }}</td>
            <td class="p-3">{{ $product->is_active ? 'Active' : 'Hidden' }}</td>
            <td class="p-3">
                <a href="{{ route('shop.show', $product->slug) }}" class="text-blue-600" target="_blank" rel="noopener">View</a>
                <a href="{{ route('admin.products.edit', $product) }}" class="text-blue-600">Edit</a>
                <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="text-red-600">Delete</button></form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
<div class="mt-4">{{ $products->links() }}</div>
@endsection
