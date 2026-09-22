(function () {
    'use strict';

    function isCleanStaffIndexUrl(url) {
        if (typeof url !== 'string' || url.length === 0) {
            return false;
        }

        if (url.indexOf('token=') !== -1 || url.indexOf('#') !== -1 || url.indexOf('?') !== -1) {
            return false;
        }

        var path = url;
        var schemeIndex = url.indexOf('://');
        if (schemeIndex !== -1) {
            var pathStart = url.indexOf('/', schemeIndex + 3);
            path = pathStart === -1 ? '/' : url.slice(pathStart);
        }

        return /\/admin\/staff\/invitations\/?$/.test(path);
    }

    var staffIndexUrl = document.body.getAttribute('data-staff-index-url');
    if (isCleanStaffIndexUrl(staffIndexUrl) && window.history && typeof window.history.replaceState === 'function') {
        window.history.replaceState({}, '', staffIndexUrl);
    }

    var input = document.getElementById('invitation-url');
    var button = document.getElementById('copy-invitation-link');
    var status = document.getElementById('invitation-copy-status');

    if (!input || !button || !status) {
        return;
    }

    button.addEventListener('click', function () {
        var text = input.value;

        function copied() {
            status.textContent = 'Link copied.';
        }

        function fallback() {
            input.focus();
            input.select();
            if (typeof input.setSelectionRange === 'function') {
                input.setSelectionRange(0, text.length);
            }
            status.textContent = 'Select the link and copy it with your keyboard.';
        }

        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(text).then(copied).catch(fallback);
            return;
        }

        fallback();
    });
})();
