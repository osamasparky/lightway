/*
 * One-stop checkout (cart/payment.blade.php).
 * Recipient choice per item, payment method and the pay button on one page.
 * The form posts the same fields as before (gateway, order_id, sale_type[], gift_user[]),
 * after a dry run on /payments/checkout-check so problems show next to the fields.
 */
(function () {
    "use strict";

    var form = document.getElementById('paymentForm');
    if (!form) {
        return;
    }

    var lang = window.lwCheckoutLang || {};
    var payBtn = document.getElementById('paymentSubmit');
    var alertBox = document.getElementById('lwCoAlert');
    var submitting = false;

    function giftBox(itemId) {
        return document.getElementById('gift-form-' + itemId);
    }

    function setGift(item, isGift) {
        var itemId = item.getAttribute('data-item');
        var box = giftBox(itemId);

        item.classList.toggle('is-gift', isGift);

        if (box) {
            box.hidden = !isGift;
            box.querySelectorAll('input').forEach(function (input) {
                input.disabled = !isGift;
            });
        }

        var summaryRow = document.querySelector('[data-summary-item="' + itemId + '"]');
        if (summaryRow) {
            summaryRow.classList.toggle('is-gift', isGift);
        }
    }

    function clearErrors() {
        form.querySelectorAll('.lw-co-field__error').forEach(function (el) {
            el.textContent = '';
        });
        form.querySelectorAll('.lw-input.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
            el.removeAttribute('aria-invalid');
        });
        alertBox.hidden = true;
        alertBox.textContent = '';
    }

    function showFieldError(key, message) {
        var slot = form.querySelector('[data-error-for="' + key + '"]');

        if (!slot) {
            return false;
        }

        slot.textContent = message;

        var input = slot.parentNode.querySelector('.lw-input');
        if (input) {
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');
            slot.id = slot.id || ('err-' + key.replace(/\./g, '-'));
            input.setAttribute('aria-describedby', slot.id);
        }

        return true;
    }

    function showAlert(message) {
        alertBox.textContent = message || lang.generic;
        alertBox.hidden = false;
    }

    function focusFirstError() {
        var first = form.querySelector('.lw-input.is-invalid');

        if (first) {
            first.focus();
            first.scrollIntoView({behavior: 'smooth', block: 'center'});
        } else if (!alertBox.hidden) {
            alertBox.scrollIntoView({behavior: 'smooth', block: 'center'});
        }
    }

    // Client-side checks for the visible gift fields (the server repeats them).
    function validateGiftFields() {
        var valid = true;
        var emails = [];

        form.querySelectorAll('.lw-co-gift:not([hidden])').forEach(function (box) {
            var itemId = box.id.replace('gift-form-', '');
            var name = box.querySelector('input[name$="[full_name]"]');
            var email = box.querySelector('input[name$="[email]"]');
            var password = box.querySelector('input[name$="[password]"]');
            var prefix = 'gift_user.' + itemId + '.';

            if (!name.value.trim()) {
                valid = showFieldError(prefix + 'full_name', lang.required) && false;
            }

            var emailValue = email.value.trim().toLowerCase();
            if (!emailValue) {
                valid = showFieldError(prefix + 'email', lang.required) && false;
            } else if (!email.checkValidity()) {
                valid = showFieldError(prefix + 'email', lang.email) && false;
            } else if (emails.indexOf(emailValue) !== -1) {
                valid = showFieldError(prefix + 'email', lang.sameEmail) && false;
            }
            emails.push(emailValue);

            if (password.value.length < 6) {
                valid = showFieldError(prefix + 'password', lang.password) && false;
            }
        });

        return valid;
    }

    function setBusy(busy) {
        submitting = busy;
        payBtn.disabled = busy;
        payBtn.classList.toggle('is-busy', busy);

        var label = payBtn.querySelector('span');
        if (busy) {
            payBtn.setAttribute('data-label', label.textContent);
            label.textContent = payBtn.getAttribute('data-processing');
        } else if (payBtn.getAttribute('data-label')) {
            label.textContent = payBtn.getAttribute('data-label');
        }
    }

    // Recipient choice
    form.querySelectorAll('.js-co-sale-type').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var item = radio.closest('.lw-co-item');
            setGift(item, radio.value === 'other' && radio.checked);

            if (radio.value === 'other') {
                var first = giftBox(item.getAttribute('data-item')).querySelector('input');
                if (first) {
                    first.focus();
                }
            }
        });
    });

    // Sync the initial state (owned items open with the gift form).
    form.querySelectorAll('.lw-co-item').forEach(function (item) {
        var checked = item.querySelector('.js-co-sale-type:checked');
        if (checked) {
            setGift(item, checked.value === 'other');
        }
    });

    // Show / hide + generate password
    form.addEventListener('click', function (e) {
        var showBtn = e.target.closest('.js-co-show-password');
        var genBtn = e.target.closest('.js-co-generate');

        if (showBtn) {
            var input = showBtn.parentNode.querySelector('input');
            var visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            showBtn.setAttribute('aria-pressed', visible ? 'false' : 'true');
            showBtn.setAttribute('aria-label', visible ? lang.show : lang.hide);
            showBtn.setAttribute('title', visible ? lang.show : lang.hide);
        }

        if (genBtn) {
            var target = genBtn.parentNode.querySelector('input');
            var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
            var bytes = new Uint32Array(10);
            (window.crypto || window.msCrypto).getRandomValues(bytes);

            var value = '';
            for (var i = 0; i < bytes.length; i++) {
                value += chars.charAt(bytes[i] % chars.length);
            }

            target.value = value;
            target.type = 'text';

            var toggle = genBtn.parentNode.querySelector('.js-co-show-password');
            toggle.setAttribute('aria-pressed', 'true');
            toggle.setAttribute('aria-label', lang.hide);
            toggle.setAttribute('title', lang.hide);
        }
    });

    // Payment method
    form.querySelectorAll('input[name="gateway"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (!submitting) {
                payBtn.disabled = false;
            }
        });
    });

    // Clear a field's error as soon as it is edited
    form.addEventListener('input', function (e) {
        if (e.target.classList.contains('is-invalid')) {
            e.target.classList.remove('is-invalid');
            e.target.removeAttribute('aria-invalid');
            var slot = e.target.closest('.lw-co-field').querySelector('.lw-co-field__error');
            if (slot) {
                slot.textContent = '';
            }
        }
    });

    form.addEventListener('submit', function (e) {
        if (form.getAttribute('data-checked') === '1') {
            return; // dry run passed, let the real request through
        }

        e.preventDefault();

        if (submitting) {
            return;
        }

        clearErrors();

        if (!form.querySelector('input[name="gateway"]:checked')) {
            showAlert(lang.generic);
            return;
        }

        if (!validateGiftFields()) {
            focusFirstError();
            return;
        }

        setBusy(true);

        fetch(form.getAttribute('data-check-url'), {
            method: 'POST',
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: new FormData(form),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (data) {
                return {ok: response.ok, data: data};
            });
        }).then(function (result) {
            if (result.ok) {
                form.setAttribute('data-checked', '1');
                form.submit();
                return;
            }

            setBusy(false);

            var errors = (result.data && result.data.errors) || {};
            var shownInline = false;

            Object.keys(errors).forEach(function (key) {
                var message = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
                if (!showFieldError(key, message)) {
                    showAlert(message);
                } else {
                    shownInline = true;
                }
            });

            if (!Object.keys(errors).length) {
                showAlert((result.data && result.data.message) || lang.generic);
            }

            if (shownInline || !alertBox.hidden) {
                focusFirstError();
            }
        }).catch(function () {
            setBusy(false);
            showAlert(lang.generic);
        });
    });
})();
