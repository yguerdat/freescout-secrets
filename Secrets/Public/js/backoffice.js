/*
 * Secrets — back-office logic.
 *  A) Create page: encrypt an outbound secret in the browser, store the
 *     ciphertext and build the zero-knowledge link (key in the fragment).
 *  B) In-ticket panel: reveal an inbound secret on demand (burn-after-read).
 */
(function () {
    'use strict';

    function t(el, name, fallback) {
        return (el && el.getAttribute('data-t-' + name)) || fallback;
    }

    function postJson(url, csrf, payload) {
        var body = new URLSearchParams();
        Object.keys(payload).forEach(function (k) { body.set(k, payload[k]); });
        return fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrf
            },
            body: body.toString()
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); });
    }

    function randomPassphrase() {
        var alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        var bytes = crypto.getRandomValues(new Uint8Array(20));
        var out = '';
        for (var i = 0; i < bytes.length; i++) {
            out += alphabet[bytes[i] % alphabet.length];
            if (i % 4 === 3 && i < bytes.length - 1) { out += '-'; }
        }
        return out;
    }

    /* --------------------------------------------------------------- A */
    function initCreate() {
        var app = document.getElementById('secrets-create-app');
        if (!app || !window.SecretsCrypto) { return; }

        var csrf = app.getAttribute('data-csrf');
        var iterations = parseInt(app.getAttribute('data-iterations'), 10) || 310000;
        var storeUrl = app.getAttribute('data-store-url');
        var smsUrl = app.getAttribute('data-sms-url');

        var secretEl = document.getElementById('sc-secret');
        var ttlEl = document.getElementById('sc-ttl');
        var viewsEl = document.getElementById('sc-views');
        var passEl = document.getElementById('sc-pass');
        var genBtn = document.getElementById('sc-gen');
        var createBtn = document.getElementById('sc-create');
        var statusEl = document.getElementById('sc-status');
        var resultWrap = document.getElementById('sc-result');
        var linkEl = document.getElementById('sc-link');
        var copyBtn = document.getElementById('sc-copy');
        var smsWrap = document.getElementById('sc-sms-wrap');
        var smsPhone = document.getElementById('sc-sms-phone');
        var smsBtn = document.getElementById('sc-sms-btn');
        var smsStatus = document.getElementById('sc-sms-status');

        function setStatus(msg, cls) { statusEl.textContent = msg || ''; statusEl.className = 'secrets-status ' + (cls || ''); }

        if (!SecretsCrypto.supported()) {
            setStatus(t(app, 'unsupported', 'Your browser does not support the required cryptography.'), 'is-error');
            createBtn.disabled = true;
            return;
        }

        if (genBtn) {
            genBtn.addEventListener('click', function () { passEl.value = randomPassphrase(); });
        }

        createBtn.addEventListener('click', function () {
            var secret = secretEl.value;
            if (!secret) { setStatus(t(app, 'required', 'Enter the secret to share.'), 'is-error'); return; }

            createBtn.disabled = true;
            setStatus(t(app, 'encrypting', 'Encrypting…'), '');
            var passphrase = passEl.value;

            SecretsCrypto.encrypt(secret, passphrase, iterations).then(function (enc) {
                return postJson(storeUrl, csrf, {
                    ciphertext: enc.ciphertextB64u,
                    salt: enc.saltB64u,
                    iv: enc.ivB64u,
                    passphrase_protected: passphrase ? '1' : '0',
                    ttl_hours: ttlEl.value,
                    max_views: viewsEl.value
                }).then(function (res) {
                    if (!res.ok || !res.j || !res.j.url) { throw new Error('server'); }
                    var link = res.j.url + '#' + enc.keyB64u;
                    linkEl.value = link;
                    resultWrap.style.display = '';
                    setStatus(t(app, 'done', 'Link ready. The secret can only be decrypted with this exact link.'), 'is-ok');
                    // Clear the plaintext from the form immediately.
                    secretEl.value = '';
                    if (passphrase && smsWrap) { smsWrap.style.display = ''; }
                });
            }).catch(function () {
                setStatus(t(app, 'error', 'Could not create the secret. Please try again.'), 'is-error');
                createBtn.disabled = false;
            });
        });

        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                linkEl.select();
                if (navigator.clipboard) { navigator.clipboard.writeText(linkEl.value); }
                else { document.execCommand('copy'); }
                copyBtn.textContent = t(app, 'copied', 'Copied');
            });
        }

        if (smsBtn) {
            smsBtn.addEventListener('click', function () {
                if (!smsPhone.value || !passEl.value) { return; }
                smsBtn.disabled = true;
                smsStatus.textContent = t(app, 'sms_sending', 'Sending…');
                postJson(smsUrl, csrf, { to: smsPhone.value, passphrase: passEl.value })
                    .then(function (res) {
                        if (res.ok && res.j && res.j.status === 'ok') {
                            smsStatus.textContent = t(app, 'sms_sent', 'Passphrase sent by SMS.');
                        } else {
                            smsStatus.textContent = (res.j && res.j.message) || t(app, 'sms_error', 'SMS failed.');
                            smsBtn.disabled = false;
                        }
                    })
                    .catch(function () { smsStatus.textContent = t(app, 'sms_error', 'SMS failed.'); smsBtn.disabled = false; });
            });
        }
    }

    /* --------------------------------------------------------------- B */
    function initInboundPanel() {
        var panel = document.getElementById('secrets-inbound-panel');
        if (!panel || !window.SecretsCrypto) { return; }

        var iterations = parseInt(panel.getAttribute('data-iterations'), 10) || 310000;
        var csrf = panel.getAttribute('data-csrf');

        var buttons = panel.querySelectorAll('.secrets-reveal-inbound');
        Array.prototype.forEach.call(buttons, function (btn) {
            btn.addEventListener('click', function () {
                var row = btn.closest('.secrets-inbound-row');
                var url = btn.getAttribute('data-reveal-url');
                var protectedFlag = btn.getAttribute('data-passphrase-protected') === '1';
                var out = row.querySelector('.secrets-inbound-output');
                var statusEl = row.querySelector('.secrets-inbound-status');
                var passInput = row.querySelector('.secrets-inbound-pass');

                var passphrase = (protectedFlag && passInput) ? passInput.value : '';
                if (protectedFlag && !passphrase) {
                    statusEl.textContent = t(panel, 'need_pass', 'Enter the passphrase the customer gave you.');
                    return;
                }

                btn.disabled = true;
                statusEl.textContent = t(panel, 'revealing', 'Revealing…');

                fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data || data.status !== 'ok') {
                            statusEl.textContent = t(panel, 'gone', 'This secret is no longer available.');
                            return Promise.reject('gone');
                        }
                        var keyBytes = SecretsCrypto.b64uDecode(data.key);
                        return SecretsCrypto.decrypt(data.ciphertext, keyBytes, data.salt, data.iv, passphrase, iterations);
                    })
                    .then(function (plaintext) {
                        out.value = plaintext;
                        out.style.display = '';
                        statusEl.textContent = t(panel, 'revealed', 'Revealed. Copy it now — it will not be shown again.');
                    })
                    .catch(function (err) {
                        if (err === 'gone') { return; }
                        statusEl.textContent = t(panel, 'wrong', 'Could not decrypt — the passphrase may be wrong.');
                        btn.disabled = false;
                    });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initCreate();
        initInboundPanel();
    });
})();
