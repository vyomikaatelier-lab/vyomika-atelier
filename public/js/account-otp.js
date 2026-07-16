(function () {
  const form = document.getElementById('account-otp-form');
  if (!form) return;

  const digits = Array.from(form.querySelectorAll('[data-otp-digit]'));
  const hidden = document.getElementById('otp-combined');
  if (!digits.length || !hidden) return;

  function combine() {
    hidden.value = digits.map((d) => d.value.replace(/\D/g, '')).join('');
  }

  function focusIndex(index) {
    const el = digits[index];
    if (el) el.focus();
  }

  digits.forEach((input, index) => {
    input.addEventListener('input', () => {
      const val = input.value.replace(/\D/g, '');
      input.value = val.slice(-1);
      combine();
      if (val && index < digits.length - 1) focusIndex(index + 1);
    });

    input.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && !input.value && index > 0) {
        focusIndex(index - 1);
      }
      if (e.key === 'ArrowLeft' && index > 0) {
        e.preventDefault();
        focusIndex(index - 1);
      }
      if (e.key === 'ArrowRight' && index < digits.length - 1) {
        e.preventDefault();
        focusIndex(index + 1);
      }
    });

    input.addEventListener('paste', (e) => {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, digits.length);
      text.split('').forEach((ch, i) => {
        if (digits[i]) digits[i].value = ch;
      });
      combine();
      focusIndex(Math.min(text.length, digits.length - 1));
    });
  });

  form.addEventListener('submit', (e) => {
    combine();
    if (hidden.value.length !== digits.length) {
      e.preventDefault();
      digits[0]?.focus();
    }
  });

  const countdown = document.getElementById('otp-resend-countdown');
  if (countdown) {
    let seconds = Number(countdown.dataset.seconds) || 0;
    const span = countdown.querySelector('span');
    const tick = () => {
      if (seconds <= 0) {
        window.location.reload();
        return;
      }
      seconds -= 1;
      if (span) span.textContent = String(seconds);
      window.setTimeout(tick, 1000);
    };
    if (seconds > 0) window.setTimeout(tick, 1000);
  }

  focusIndex(0);
})();
