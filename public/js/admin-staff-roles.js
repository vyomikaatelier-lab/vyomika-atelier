(function () {
    'use strict';

    var editor = document.querySelector('[data-staff-role-editor]');
    if (!editor) {
        return;
    }

    var form = editor.querySelector('[data-role-permission-form]');
    var status = editor.querySelector('[data-unsaved-status]');
    var picker = editor.querySelector('[data-role-picker]');
    var initialSerialized = form ? serializeForm(form) : '';

    function serializeForm(target) {
        var data = new FormData(target);
        var pairs = [];
        data.forEach(function (value, key) {
            pairs.push(encodeURIComponent(key) + '=' + encodeURIComponent(String(value)));
        });
        pairs.sort();
        return pairs.join('&');
    }

    function hasUnsavedChanges() {
        return Boolean(form) && serializeForm(form) !== initialSerialized;
    }

    function syncSwitchState(input) {
        var checked = Boolean(input.checked);
        var stateWord = checked ? 'on' : 'off';
        var role = input.getAttribute('data-switch-role') || '';
        var permission = input.getAttribute('data-switch-permission') || '';
        var lockReason = input.getAttribute('data-switch-lock') || '';
        var label = role + ': ' + permission + ', ' + stateWord;

        if (lockReason) {
            label += ', locked. ' + lockReason;
        }

        input.setAttribute('aria-checked', checked ? 'true' : 'false');
        input.setAttribute('aria-label', label);

        var stateNode = input.parentElement && input.parentElement.querySelector('.staff-switch-state');
        if (stateNode) {
            stateNode.textContent = checked ? 'On' : 'Off';
        }
    }

    function refreshUnsaved() {
        var dirty = hasUnsavedChanges();
        if (status) {
            status.hidden = !dirty;
        }
        editor.classList.toggle('has-unsaved-changes', dirty);
    }

    function confirmLeave() {
        if (!hasUnsavedChanges()) {
            return true;
        }

        return window.confirm('Discard unsaved permission changes for this role?');
    }

    editor.querySelectorAll('.staff-switch-input').forEach(function (input) {
        syncSwitchState(input);
        input.addEventListener('change', function () {
            syncSwitchState(input);
            refreshUnsaved();
        });
    });

    if (form) {
        form.addEventListener('reset', function () {
            window.setTimeout(function () {
                form.querySelectorAll('.staff-switch-input').forEach(syncSwitchState);
                refreshUnsaved();
            }, 0);
        });
    }

    editor.querySelectorAll('[data-role-link]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            if (!confirmLeave()) {
                event.preventDefault();
            }
        });
    });

    var cancel = editor.querySelector('[data-cancel-permissions]');
    if (cancel) {
        cancel.addEventListener('click', function (event) {
            if (!confirmLeave()) {
                event.preventDefault();
            }
        });
    }

    if (picker) {
        picker.addEventListener('change', function () {
            if (!confirmLeave()) {
                picker.value = editor.getAttribute('data-selected-role') || picker.value;
                return;
            }

            picker.form.submit();
        });
    }

    refreshUnsaved();
})();
