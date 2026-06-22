/*
 * Secrets — customer intake form logic.
 * The secret is encrypted in the browser and the content key is wrapped to the
 * module RSA public key before anything is sent. The server only ever receives
 * ciphertext + an RSA-wrapped key.
 */
(function () {
    'use strict';

    function t(el, name, fallback) {
        return el.getAttribute('data-t-' + name) || fallback;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var app = document.getElementById('secrets-app');
        if (!app) { return; }

        var form = document.getElementById('secrets-form');
        var formWrap = document.getElementById('secrets-form-wrap');
        var successWrap = document.getElementById('secrets-success');
        var statusEl = document.getElementById('secrets-status');
        var submitBtn = document.getElementById('secrets-submit');

        var iterations = parseInt(app.getAttribute('data-iterations'), 10) || 310000;
        var pubkeyUrl = app.getAttribute('data-pubkey-url');
        var inboundUrl = app.getAttribute('data-inbound-url');

        function setStatus(msg, cls) {
            statusEl.textContent = msg || '';
            statusEl.className = 'secrets-status ' + (cls || '');
        }

        if (!window.SecretsCrypto || !SecretsCrypto.supported()) {
            setStatus(t(app, 'unsupported', 'Your browser does not support the required cryptography.'), 'is-error');
            if (submitBtn) { submitBtn.disabled = true; }
            return;
        }

        var rsaKeyPromise = fetch(pubkeyUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.public_key) { throw new Error('no key'); }
                return SecretsCrypto.importRsaPublicKey(d.public_key);
            });

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var secret = form.querySelector('[name=secret]').value;
            var email = form.querySelector('[name=email]').value.trim();
            var subject = form.querySelector('[name=subject]').value.trim();
            var note = form.querySelector('[name=note]').value.trim();
            var passphrase = form.querySelector('[name=passphrase]').value;
            var honeypot = form.querySelector('[name=website]').value;

            if (!secret || !email) {
                setStatus(t(app, 'required', 'Please provide your e-mail and the information to send.'), 'is-error');
                return;
            }

            submitBtn.disabled = true;
            setStatus(t(app, 'encrypting', 'Encrypting in your browser…'), '');

            var enc;
            rsaKeyPromise
                .then(function (rsaKey) {
                    return SecretsCrypto.encrypt(secret, passphrase, iterations).then(function (r) {
                        enc = r;
                        return SecretsCrypto.wrapKey(r.rawKey, rsaKey);
                    });
                })
                .then(function (wrappedKey) {
                    var body = new URLSearchParams();
                    body.set('ciphertext', enc.ciphertextB64u);
                    body.set('salt', enc.saltB64u);
                    body.set('iv', enc.ivB64u);
                    body.set('wrapped_key', wrappedKey);
                    body.set('passphrase_protected', passphrase ? '1' : '0');
                    body.set('email', email);
                    body.set('subject', subject);
                    body.set('note', note);
                    body.set('website', honeypot);

                    return fetch(inboundUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: body.toString()
                    });
                })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (!res.ok || !res.j || res.j.status !== 'ok') {
                        throw new Error('server');
                    }
                    formWrap.style.display = 'none';
                    successWrap.style.display = '';
                    setStatus('', '');
                })
                .catch(function () {
                    setStatus(t(app, 'error', 'Could not send the secret. Please try again.'), 'is-error');
                    submitBtn.disabled = false;
                });
        });
    });
})();
