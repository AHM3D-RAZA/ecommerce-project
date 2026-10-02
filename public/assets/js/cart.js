/*
 * "Add to cart" without leaving the page.
 *
 * Add-to-cart links (product cards) and forms (product page) point at
 * cart-action.php. With JavaScript we send them in the background, show a toast
 * to acknowledge it and refresh the header cart count. Without JavaScript the
 * same request still works server-side - it just returns you to the page you
 * came from instead of the cart page.
 */
(function () {
    'use strict';

    var FAILED = 'Sorry, we could not add that to your cart. Please try again.';

    function toastBox() {
        var box = document.getElementById('cart-toasts');

        if (box) {
            return box;
        }

        box = document.createElement('div');
        box.id = 'cart-toasts';
        box.setAttribute('role', 'status');
        box.setAttribute('aria-live', 'polite');
        // Bottom-right so a toast never covers the header / nav, and newest
        // ends up nearest the corner.
        box.style.cssText = 'position:fixed;right:20px;bottom:24px;z-index:1090;' +
            'display:flex;flex-direction:column-reverse;align-items:flex-end;gap:.5rem;' +
            'pointer-events:none;max-width:min(360px,calc(100vw - 40px));';
        document.body.appendChild(box);

        return box;
    }

    function showToast(message, ok) {
        var el = document.createElement('div');
        el.className = 'toast show align-items-center border-0 rounded-3 shadow-lg';
        el.setAttribute('role', 'alert');
        // Solid colours on purpose: Bootstrap's .text-bg-* is translucent with
        // black text, which reads as washed out at this size.
        el.style.cssText = 'pointer-events:auto;width:auto;color:#fff;background:'
            + (ok ? '#2f9e44' : '#e03131') + ';';

        var row = document.createElement('div');
        row.className = 'd-flex align-items-center';

        var icon = document.createElement('span');
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = ok ? '✓' : '!';
        icon.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;' +
            'flex:0 0 auto;width:22px;height:22px;margin-left:.85rem;border-radius:50%;' +
            'background:rgba(255,255,255,.25);font-size:.8rem;font-weight:700;';

        var body = document.createElement('div');
        body.className = 'toast-body';
        // textContent, never innerHTML - the text comes back from the server.
        body.textContent = message;
        body.style.cssText = 'padding-left:.7rem;font-weight:500;color:#fff;';

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close';
        close.setAttribute('data-bs-dismiss', 'toast');
        close.setAttribute('aria-label', 'Close');
        // .btn-close is a dark X; invert it so it shows on the coloured toast.
        close.style.cssText = 'margin-right:.85rem;filter:invert(1) brightness(1.6);opacity:.85;';

        close.addEventListener('click', function () { closeToast(el); });

        row.appendChild(icon);
        row.appendChild(body);
        row.appendChild(close);
        el.appendChild(row);
        toastBox().appendChild(el);

        if (window.bootstrap && typeof window.bootstrap.Toast === 'function') {
            var toast = new window.bootstrap.Toast(el, { delay: 2800 });
            el.addEventListener('hidden.bs.toast', function () { remove(); });
            toast.show();
        } else {
            setTimeout(function () { remove(); }, 2800);
        }

        function remove() {
            if (el.parentNode) {
                el.remove();
            }
        }

        function closeToast(node) {
            if (window.bootstrap && typeof window.bootstrap.Toast === 'function') {
                var t = window.bootstrap.Toast.getInstance(node);
                if (t) { t.hide(); } else { node.remove(); }
            } else {
                node.remove();
            }
        }
    }

    function updateCartCount(count) {
        var badges = document.querySelectorAll('.cart-count');

        Array.prototype.forEach.call(badges, function (badge) {
            badge.textContent = count;

            if (typeof badge.animate === 'function') {
                badge.animate(
                    [{ transform: 'scale(1)' }, { transform: 'scale(1.3)' }, { transform: 'scale(1)' }],
                    { duration: 320, easing: 'ease-out' }
                );
            }
        });
    }

    function send(url, options, done) {
        fetch(url, options)
            .then(function (response) { return response.json(); })
            .then(function (data) {
                updateCartCount(data.count);
                showToast(data.message, data.success);
            })
            .catch(function () { showToast(FAILED, false); })
            .then(function () { done(); });
    }

    // Product cards: an <a> styled exactly as the template expects, but
    // upgraded to an async request so the page doesn't change.
    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[data-cart-add]');

        if (!link || link.dataset.busy === '1') {
            return;
        }

        event.preventDefault();
        link.dataset.busy = '1';

        send(link.href, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }, function () { link.dataset.busy = '0'; });
    });

    // Product page: the form carries the quantity.
    document.addEventListener('submit', function (event) {
        var form = event.target.closest('form[data-cart-add]');

        if (!form || form.dataset.busy === '1') {
            return;
        }

        event.preventDefault();
        form.dataset.busy = '1';

        send(form.getAttribute('action'), {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }, function () { form.dataset.busy = '0'; });
    });
})();