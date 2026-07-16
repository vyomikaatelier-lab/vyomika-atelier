@props(['current' => 1])

@php
    $steps = [
        1 => ['label' => 'Cart', 'route' => 'cart.index'],
        2 => ['label' => 'Details', 'route' => 'checkout.index'],
        3 => ['label' => 'Payment', 'route' => null],
        4 => ['label' => 'Confirmed', 'route' => null],
    ];
@endphp

<nav class="am-checkout-steps" aria-label="Checkout progress">
    <ol class="am-checkout-steps__list">
        @foreach($steps as $num => $step)
            @php
                $isComplete = $num < $current;
                $isCurrent = $num === $current;
                $isUpcoming = $num > $current;
            @endphp
            <li class="am-checkout-steps__item {{ $isComplete ? 'is-complete' : '' }} {{ $isCurrent ? 'is-current' : '' }} {{ $isUpcoming ? 'is-upcoming' : '' }}">
                @if($isComplete && $step['route'])
                    <a href="{{ route($step['route']) }}" class="am-checkout-steps__link">
                        <span class="am-checkout-steps__dot" aria-hidden="true">{{ $num }}</span>
                        <span class="am-checkout-steps__label">{{ $step['label'] }}</span>
                    </a>
                @else
                    <span class="am-checkout-steps__link" @if($isCurrent) aria-current="step" @endif>
                        <span class="am-checkout-steps__dot" aria-hidden="true">{{ $num }}</span>
                        <span class="am-checkout-steps__label">{{ $step['label'] }}</span>
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
