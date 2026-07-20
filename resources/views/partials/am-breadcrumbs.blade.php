@props(['items' => [], 'class' => ''])
@if(count($items))
<nav class="am-breadcrumbs{{ $class ? ' ' . $class : '' }}" aria-label="Breadcrumb">
    <ol>
        @foreach($items as $item)
        <li>
            @if(!empty($item['url']))
            <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
            @else
            <span aria-current="page">{{ $item['label'] }}</span>
            @endif
        </li>
        @endforeach
    </ol>
</nav>
@endif
