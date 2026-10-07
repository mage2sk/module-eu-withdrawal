define([], function () {
    'use strict';

    return function (config, form) {
        var select = form.querySelector('#panth-euw-status');
        var comment = form.querySelector('#panth-euw-status-comment');
        var marker = form.querySelector('#panth-euw-comment-required');
        var error = form.querySelector('#panth-euw-comment-error');

        if (!select || !comment || !marker || !error) {
            return;
        }

        var current = select.getAttribute('data-current');
        var rejected = select.getAttribute('data-rejected');

        function needsReason() {
            return select.value === rejected && current !== rejected;
        }

        function hideError() {
            error.hidden = true;
            comment.removeAttribute('aria-invalid');
        }

        function sync() {
            var required = needsReason();

            comment.required = required;
            comment.setAttribute('aria-required', required ? 'true' : 'false');
            marker.hidden = !required;
            if (!required) {
                hideError();
            }
        }

        select.addEventListener('change', sync);
        comment.addEventListener('input', function () {
            if (comment.value.trim() !== '') {
                hideError();
            }
        });
        form.addEventListener('submit', function (event) {
            if (needsReason() && comment.value.trim() === '') {
                event.preventDefault();
                event.stopImmediatePropagation();
                error.hidden = false;
                comment.setAttribute('aria-invalid', 'true');
                comment.focus();
            }
        });
        sync();
    };
});
