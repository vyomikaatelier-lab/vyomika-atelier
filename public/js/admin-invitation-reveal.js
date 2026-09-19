(function () {
    'use strict';

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
