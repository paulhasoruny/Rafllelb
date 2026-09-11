(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var button = document.getElementById('rlb-ai-test-connection');
        var result = document.getElementById('rlb-ai-test-result');
        var config = window.RaffleLBAIAdmin;
        if (!button || !result || !config) {
            return;
        }

        button.addEventListener('click', function () {
            button.disabled = true;
            result.textContent = 'Testing…';
            var body = new URLSearchParams();
            body.set('action', 'rafflelb_ai_test_connection');
            body.set('nonce', config.nonce);

            fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            }).then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (data) {
                    if (!response.ok || !data.success || !data.data || typeof data.data.message !== 'string') {
                        throw new Error('connection_failed');
                    }
                    return data.data.message;
                });
            }).then(function (message) {
                result.textContent = message;
                result.setAttribute('data-status', 'success');
            }).catch(function () {
                result.textContent = 'Connection failed.';
                result.setAttribute('data-status', 'error');
            }).finally(function () {
                button.disabled = false;
            });
        });
    });
}());

