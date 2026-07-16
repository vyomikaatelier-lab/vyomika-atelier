document.addEventListener('DOMContentLoaded', () => {
  const switcher = document.querySelector('[data-account-login-switch]');
  const panels = document.querySelectorAll('[data-login-panel]');

  const setLoginPanel = (target) => {
    panels.forEach((panel) => {
      panel.classList.toggle('is-hidden', panel.dataset.loginPanel !== target);
    });

    if (!switcher) {
      return;
    }

    const links = {
      email: '<button type="button" data-login-panel-target="mobile-otp">Sign in with WhatsApp OTP</button><span aria-hidden="true">·</span><button type="button" data-login-panel-target="mobile-password">Sign in with mobile &amp; password</button>',
      'mobile-otp': '<button type="button" data-login-panel-target="email">Sign in with email</button><span aria-hidden="true">·</span><button type="button" data-login-panel-target="mobile-password">Sign in with mobile &amp; password</button>',
      'mobile-password': '<button type="button" data-login-panel-target="email">Sign in with email</button><span aria-hidden="true">·</span><button type="button" data-login-panel-target="mobile-otp">Sign in with WhatsApp OTP</button>',
    };

    switcher.innerHTML = links[target] || links.email;
  };

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-login-panel-target]');
    if (!button) {
      return;
    }
    event.preventDefault();
    setLoginPanel(button.dataset.loginPanelTarget);
  });
});
