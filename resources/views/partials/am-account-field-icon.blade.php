@props(['icon' => 'user'])

<span class="am-account-field-input__icon" aria-hidden="true">
@switch($icon)
    @case('email')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
        @break
    @case('password')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M12 3a4 4 0 0 1 4 4v2H8V7a4 4 0 0 1 4-4z"/><rect x="5" y="9" width="14" height="12" rx="2"/><circle cx="12" cy="15" r="1.25" fill="currentColor" stroke="none"/></svg>
        @break
    @case('user')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="8" r="4"/><path d="M5 20c0-3.5 3.1-6 7-6s7 2.5 7 6"/></svg>
        @break
    @case('phone')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="7" y="2.5" width="10" height="19" rx="2"/><path d="M11 5h2M10 18h4"/></svg>
        @break
    @case('location')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M12 21s6-5.2 6-10a6 6 0 1 0-12 0c0 4.8 6 10 6 10z"/><circle cx="12" cy="11" r="2.25"/></svg>
        @break
    @case('badge')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="4" y="6" width="16" height="12" rx="2"/><path d="M8 10h8M8 14h5"/></svg>
        @break
    @case('otp')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 12h.01M12 12h.01M17 12h.01"/></svg>
        @break
    @default
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="9"/></svg>
@endswitch
</span>
