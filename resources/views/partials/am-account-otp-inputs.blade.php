<div class="am-account-otp {{ ! empty($disabled) ? 'is-disabled' : '' }}" data-otp-length="6" role="group" aria-label="6-digit verification code">
    @for($i = 0; $i < 6; $i++)
    <input type="text"
        class="am-account-otp__digit"
        maxlength="1"
        inputmode="numeric"
        pattern="[0-9]"
        autocomplete="one-time-code"
        aria-label="Digit {{ $i + 1 }} of 6"
        data-otp-digit
        @disabled(! empty($disabled))>
    @endfor
</div>
