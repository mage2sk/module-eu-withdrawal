/**
 * Panth EU Withdrawal — modal opener + bot-trap stamping.
 *
 * Vanilla JS, no dependencies, no inline handlers (Content-Security-Policy
 * safe) and no server-rendered tokens (Full-Page-Cache safe). Works on both
 * Hyva and Luma.
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    /**
     * For logged-in customers: turn the order-number text field into a
     * dropdown of their eligible orders and pre-fill name/email. Falls back
     * silently for guests, errors, or no-JS. FPC-safe (session AJAX).
     */
    function enhanceForLoggedInCustomer(form) {
        var url = form.getAttribute('data-euw-orders-url');
        if (!url) {
            return;
        }
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json();
        }).then(function (data) {
            if (!data || !data.loggedIn) {
                return;
            }
            var emailField = form.querySelector('input[name="email"]');
            if (emailField && data.email) {
                emailField.value = data.email;
                emailField.readOnly = true;
            }
            var nameField = form.querySelector('input[name="name"]');
            if (nameField && data.name) {
                nameField.value = data.name;
                nameField.readOnly = true;
            }

            var orderInput = form.querySelector('[name="increment_id"]');
            if (data.orders && data.orders.length && orderInput) {
                var select = document.createElement('select');
                select.name = 'increment_id';
                select.className = orderInput.className;
                select.required = true;
                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = '— ' + 'Select your order' + ' —';
                select.appendChild(placeholder);
                data.orders.forEach(function (o) {
                    var opt = document.createElement('option');
                    opt.value = o.id;
                    opt.textContent = o.label;
                    select.appendChild(opt);
                });
                orderInput.parentNode.replaceChild(select, orderInput);
            } else if (orderInput) {
                // Logged in but no eligible orders — guide the customer.
                var msg = form.querySelector('[data-euw-message]');
                if (msg) {
                    msg.textContent = 'We could not find any recent orders on your account that are still within the withdrawal period. If you ordered as a guest, enter your order number and email below.';
                    msg.classList.remove('panth-euw-hidden');
                }
            }
        }).catch(function () { /* keep the plain form on any failure */ });
    }

    ready(function () {
        /* ---- Bot trap: prove JS ran and measure on-screen time ---- */
        var forms = document.querySelectorAll('.panth-euw-form');
        forms.forEach(function (form) {
            var jsField = form.querySelector('input[name="panth_js"]');
            var dtField = form.querySelector('input[name="panth_dt"]');
            var shownAt = Date.now();
            if (jsField) {
                jsField.value = '1';
            }
            form.addEventListener('submit', function () {
                if (dtField) {
                    dtField.value = String(Date.now() - shownAt);
                }
            });
            enhanceForLoggedInCustomer(form);
        });

        /* ---- Modal ---- */
        var modal = document.getElementById('panth-euw-modal');
        if (!modal) {
            return;
        }
        var lastFocused = null;

        function open(e) {
            if (e) {
                e.preventDefault();
            }
            lastFocused = document.activeElement;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.documentElement.style.overflow = 'hidden';
            // Reset the on-screen timer for the modal form so the speed-trap is fair.
            var mForm = modal.querySelector('.panth-euw-form');
            if (mForm) {
                mForm.dataset.shownAt = String(Date.now());
            }
            var first = modal.querySelector('input:not([type=hidden]), textarea');
            if (first) {
                setTimeout(function () { first.focus(); }, 60);
            }
        }

        function close() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.documentElement.style.overflow = '';
            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
        }

        document.querySelectorAll('[data-panth-euw-open]').forEach(function (trigger) {
            trigger.addEventListener('click', open);
        });
        modal.querySelectorAll('[data-panth-euw-close]').forEach(function (el) {
            el.addEventListener('click', close);
        });
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                close();
            }
        });
        document.addEventListener('keydown', function (e) {
            if ((e.key === 'Escape' || e.key === 'Esc') && modal.classList.contains('is-open')) {
                close();
            }
        });

        // Keep the modal form's speed-trap accurate when re-opened.
        var modalForm = modal.querySelector('.panth-euw-form');
        if (modalForm) {
            var dt = modalForm.querySelector('input[name="panth_dt"]');
            modalForm.addEventListener('submit', function () {
                if (dt && modalForm.dataset.shownAt) {
                    dt.value = String(Date.now() - parseInt(modalForm.dataset.shownAt, 10));
                }
            });
        }
    });
})();
