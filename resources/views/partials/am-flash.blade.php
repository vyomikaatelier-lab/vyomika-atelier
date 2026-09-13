@php
    $flashItems = [];

    if (session('success')) {
        $flashItems[] = ['type' => 'success', 'role' => 'status', 'text' => (string) session('success')];
    }
    if (session('info')) {
        $flashItems[] = ['type' => 'info', 'role' => 'status', 'text' => (string) session('info')];
    }
    if (session('error')) {
        $flashItems[] = ['type' => 'error', 'role' => 'alert', 'text' => (string) session('error')];
    }
    if (session(\App\Services\CartService::NOTICE_KEY)) {
        foreach ((array) session(\App\Services\CartService::NOTICE_KEY) as $notice) {
            if (filled($notice)) {
                $flashItems[] = ['type' => 'info', 'role' => 'status', 'text' => (string) $notice];
            }
        }
    }
    $formErrors = isset($errors) && $errors->any() ? $errors->all() : [];
@endphp
@if($flashItems !== [] || $formErrors !== [])
<div class="am-flash" data-am-flash aria-live="polite">
    @foreach($flashItems as $item)
    <div class="am-alert am-alert--{{ $item['type'] }}" role="{{ $item['role'] }}">
        <p class="am-alert__text">{{ $item['text'] }}</p>
        <button type="button" class="am-alert__close" aria-label="Dismiss notification">&times;</button>
    </div>
    @endforeach
    @if($formErrors !== [])
    <div class="am-alert am-alert--error" role="alert">
        <p class="am-alert__text">@foreach($formErrors as $error){{ $error }}@if(!$loop->last) · @endif @endforeach</p>
        <button type="button" class="am-alert__close" aria-label="Dismiss notification">&times;</button>
    </div>
    @endif
</div>
@endif
