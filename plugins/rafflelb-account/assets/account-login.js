/* RaffleLB glass account access. Enhance existing forms without replacing them. */
(function () {
    'use strict';
    var assetBase = document.currentScript && document.currentScript.src;
    function init() {
        if (document.body.classList.contains('logged-in')) return;
        var login = document.querySelector('.woocommerce form.woocommerce-form-login');
        if (!login || login.dataset.rlGlass) return;
        login.dataset.rlGlass = '1';
        var wrap = document.getElementById('customer_login');
        var woo = login.closest('.woocommerce');
        document.body.classList.add('rl-glass-login');
        woo.classList.add('rl-glass-stage');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.id = 'customer_login';
            login.parentNode.insertBefore(wrap, login);
            var card = document.createElement('div');
            wrap.appendChild(card);
            card.appendChild(login);
        }
        // WoodMart can keep both forms in one column and a separate Register promo.
        // Give each real form its own card; remove only the now-empty theme columns.
        var forms = Array.prototype.slice.call(wrap.querySelectorAll('form.login, form.register, .woocommerce-form-login, .woocommerce-form-register'));
        var oldChildren = Array.prototype.slice.call(wrap.children);
        var cards = [];
        forms.forEach(function (form) {
            var card = document.createElement('div');
            wrap.appendChild(card);
            card.appendChild(form);
            cards.push(card);
            card.classList.add('rl-glass-card');
            var isLogin = form === login;
            card.classList.add(isLogin ? 'rl-glass-signin' : 'rl-glass-register');
            var emblem = document.createElement('div');
            emblem.className = 'rl-glass-avatar';
            emblem.setAttribute('aria-hidden', 'true');
            var logo = document.createElement('img');
            logo.src = new URL('rafflelb-site-icon.png', assetBase || location.href).href;
            logo.alt = ''; logo.width = 88; logo.height = 88;
            emblem.appendChild(logo);
            card.insertBefore(emblem, card.firstChild);
            var title = card.querySelector('h2');
            if (!title) { title = document.createElement('h2'); card.insertBefore(title, form); }
            title.textContent = isLogin ? 'Welcome back' : 'Create your account';
            var subtitle = document.createElement('p');
            subtitle.className = 'rl-glass-subtitle';
            subtitle.textContent = isLogin ? 'Your next chance starts here.' : 'Join RaffleLB. Be part of the excitement.';
            title.insertAdjacentElement('afterend', subtitle);
            form.querySelectorAll('input.input-text').forEach(function (input) {
                if (!input.placeholder) {
                    var label = input.id && Array.prototype.find.call(form.querySelectorAll('label'), function (el) { return el.htmlFor === input.id; });
                    var labelCopy = label && label.cloneNode(true);
                    if (labelCopy) labelCopy.querySelectorAll('.required, .screen-reader-text').forEach(function (el) { el.remove(); });
                    input.placeholder = labelCopy ? labelCopy.textContent.replace(/\*/g, '').trim() : input.name;
                }
                var row = input.closest('.form-row');
                if (row) row.classList.add('rl-glass-field');
                // Anchor the icon to the input, independent of theme label/wrapper spacing.
                var control = input.closest('.password-input') || input;
                var field = document.createElement('span');
                field.className = 'rl-glass-control';
                control.parentNode.insertBefore(field, control);
                field.appendChild(control);
                var icon = document.createElement('span');
                icon.className = 'rl-glass-field-icon'; icon.setAttribute('aria-hidden', 'true');
                icon.innerHTML = input.type === 'password'
                    ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>'
                    : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/></svg>';
                field.appendChild(icon);
                if (input.type === 'password') {
                    var toggle = document.createElement('button');
                    toggle.type = 'button'; toggle.className = 'rl-glass-password-toggle';
                    toggle.setAttribute('aria-label', 'Show password');
                    toggle.setAttribute('aria-pressed', 'false');
                    if (input.id) toggle.setAttribute('aria-controls', input.id);
                    toggle.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
                    toggle.addEventListener('click', function () {
                        var show = input.type === 'password';
                        input.type = show ? 'text' : 'password';
                        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                        toggle.setAttribute('aria-pressed', String(show));
                    });
                    field.appendChild(toggle);
                }
            });
            if (isLogin) {
                var remember = form.querySelector('.woocommerce-form-login__rememberme, .woocommerce-form__label-for-checkbox');
                var lost = form.querySelector('.lost_password, .woocommerce-LostPassword');
                var submit = form.querySelector('button[name="login"], .woocommerce-form-login__submit');
                if (submit && (remember || lost)) {
                    var options = document.createElement('div');
                    options.className = 'rl-glass-options';
                    var submitRow = submit.closest('.form-row');
                    form.insertBefore(options, submitRow || submit);
                    if (remember) options.appendChild(remember);
                    if (lost) options.appendChild(lost);
                }
            }
        });
        oldChildren.forEach(function (el) {
            // The forms (including their hooks/nonces) have already moved into our cards.
            // Retire all original layout wrappers, regardless of theme-specific classes.
            var notices = el.matches('.woocommerce-notices-wrapper, .woocommerce-error, .woocommerce-message, .woocommerce-info')
                ? [el] : Array.prototype.slice.call(el.querySelectorAll('.woocommerce-notices-wrapper, .woocommerce-error, .woocommerce-message, .woocommerce-info'));
            notices.forEach(function (notice) { woo.insertBefore(notice, wrap); });
            if (!notices.includes(el)) el.remove();
        });
        // Also remove standalone theme login/register headings and promos outside the grid.
        woo.querySelectorAll('.registration-info, .login-info, .wd-login-divider, .wd-switch-to-register').forEach(function (el) {
            if (!el.closest('.rl-glass-card')) el.remove();
        });
        woo.querySelectorAll('h2, h3').forEach(function (el) {
            if (!el.closest('.rl-glass-card') && /^(login|log in|register)$/i.test(el.textContent.trim())) el.remove();
        });
        var ancestor = woo.parentElement;
        while (ancestor && ancestor !== document.body) {
            ancestor.classList.add('rl-glass-shell');
            ancestor = ancestor.parentElement;
        }
        var signIn = cards.find(function (card) { return card.contains(login); });
        var register = cards.find(function (card) { return card !== signIn; });
        if (signIn && register) {
            function showRegistration(show, focus) {
                signIn.hidden = show;
                register.hidden = !show;
                if (focus) {
                    var heading = (show ? register : signIn).querySelector('h2');
                    heading.tabIndex = -1;
                    heading.focus();
                }
            }
            function addSwitch(card, text, label, show) {
                var row = document.createElement('p'); row.className = 'rl-glass-switch';
                row.appendChild(document.createTextNode(text + ' '));
                var button = document.createElement('button'); button.type = 'button'; button.textContent = label;
                button.addEventListener('click', function () { showRegistration(show, true); });
                row.appendChild(button); card.appendChild(row);
            }
            var registerButton = document.createElement('button');
            registerButton.type = 'button';
            registerButton.className = 'rl-glass-register-button';
            registerButton.textContent = 'Register';
            registerButton.addEventListener('click', function () { showRegistration(true, true); });
            var loginButton = login.querySelector('button[name="login"], .woocommerce-form-login__submit');
            if (loginButton) loginButton.insertAdjacentElement('afterend', registerButton);
            else signIn.appendChild(registerButton);
            addSwitch(register, 'Already a member?', 'Sign in', false);
            // Preserve the registration view after a failed registration POST.
            showRegistration(!!register.querySelector('input:not([type="hidden"]):not([type="password"]):not([type="checkbox"])[value]:not([value=""])') || location.hash === '#register', false);
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
