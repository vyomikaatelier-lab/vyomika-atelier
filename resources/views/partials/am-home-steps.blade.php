@php
    use App\Support\HomeSections;

    $howItWorks = HomeSections::howItWorks();
@endphp

@if(!empty($howItWorks['steps']))
<section class="am-section am-section--edge am-steps-section" id="how-it-works" aria-labelledby="am-steps-title">
    <div class="am-section__intro">
        <div class="am-section-head">
            <h2 id="am-steps-title">{{ $howItWorks['title'] }}</h2>
            @if(filled($howItWorks['subtitle']))
            <p>{{ $howItWorks['subtitle'] }}</p>
            @endif
        </div>
    </div>
    <div class="am-section__body">
        <ol class="am-steps">
            @foreach($howItWorks['steps'] as $i => $step)
            <li class="am-step">
                <span class="am-step__number" aria-hidden="true">{{ $i + 1 }}</span>
                <h3 class="am-step__title">{{ $step['title'] }}</h3>
                @if(filled($step['description'] ?? null))
                <p class="am-step__text">{{ $step['description'] }}</p>
                @endif
            </li>
            @endforeach
        </ol>
    </div>
</section>
@endif
