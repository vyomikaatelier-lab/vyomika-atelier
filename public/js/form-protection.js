(function () {
    'use strict';

    var TURNSTILE_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
    var LOAD_TIMEOUT_MS = 8000;
    var scriptRequested = false;

    function loadTurnstileScript() {
        if (scriptRequested) {
            return Promise.resolve();
        }
        scriptRequested = true;

        return new Promise(function (resolve, reject) {
            var existing = document.querySelector('script[src*="challenges.cloudflare.com/turnstile"]');
            if (existing) {
                existing.addEventListener('load', function () { resolve(); });
                existing.addEventListener('error', function () { reject(); });
                if (window.turnstile) {
                    resolve();
                }
                return;
            }

            var script = document.createElement('script');
            script.src = TURNSTILE_SRC;
            script.async = true;
            script.defer = true;
            script.onload = function () { resolve(); };
            script.onerror = function () { reject(); };
            document.head.appendChild(script);
        });
    }

    function showFallback(container) {
        var fallback = container.querySelector('[data-turnstile-fallback]');
        var unavailable = container.querySelector('[data-turnstile-unavailable]');
        if (fallback) {
            fallback.hidden = false;
        }
        if (unavailable) {
            unavailable.value = '1';
        }
    }

    function renderWidget(container) {
        var widgetHost = container.querySelector('[data-turnstile-widget]');
        if (!widgetHost || !widgetHost.dataset.sitekey) {
            showFallback(container);
            return;
        }

        if (!window.turnstile) {
            showFallback(container);
            return;
        }

        if (widgetHost.dataset.rendered === '1') {
            return;
        }

        widgetHost.dataset.rendered = '1';
        window.turnstile.render(widgetHost, {
            sitekey: widgetHost.dataset.sitekey,
            theme: 'light',
            appearance: widgetHost.dataset.appearance || 'always',
            size: 'normal',
        });
    }

    function initContainer(container) {
        var widgetHost = container.querySelector('[data-turnstile-widget]');
        if (!widgetHost || !widgetHost.dataset.sitekey) {
            showFallback(container);
            return;
        }

        var timedOut = false;
        var timeout = window.setTimeout(function () {
            timedOut = true;
            if (!widgetHost.dataset.rendered) {
                showFallback(container);
            }
        }, LOAD_TIMEOUT_MS);

        loadTurnstileScript()
            .then(function () {
                if (!timedOut) {
                    renderWidget(container);
                }
            })
            .catch(function () {
                showFallback(container);
            })
            .finally(function () {
                window.clearTimeout(timeout);
            });
    }

    function init() {
        document.querySelectorAll('[data-form-protection]').forEach(initContainer);
    }

    function refreshFormLoadedAt(form) {
        if (!form) {
            return Promise.resolve();
        }

        var container = form.querySelector('[data-form-protection]');
        var formKey = container && container.getAttribute('data-form-key');
        var input = form.querySelector('input[name="form_loaded_at"]');
        if (!formKey || !input) {
            return Promise.resolve();
        }

        return fetch('/form-protection/token/' + encodeURIComponent(formKey), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('token refresh failed');
                }
                return response.json();
            })
            .then(function (data) {
                if (data && data.form_loaded_at) {
                    input.value = data.form_loaded_at;
                }
            })
            .catch(function () {
                /* keep existing token — server may still accept within max age */
            });
    }

    window.vaFormProtection = {
        refreshFormLoadedAt: refreshFormLoadedAt,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
