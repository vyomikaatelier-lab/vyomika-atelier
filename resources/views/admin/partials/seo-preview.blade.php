@props([
    'title' => '',
    'description' => '',
    'url' => '',
])

<div {{ $attributes->merge(['class' => 'border rounded p-3 bg-gray-50 text-sm']) }}>
    <p class="text-green-800 font-medium truncate">{{ $title !== '' ? $title : 'Title preview' }}</p>
    <p class="text-blue-700 text-xs truncate">{{ $url }}</p>
    <p class="text-gray-600 line-clamp-2">{{ $description }}</p>
</div>
