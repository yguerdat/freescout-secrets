/*
 * Secrets — reveal page logic (outbound secrets).
 * The ciphertext is fetched and decrypted entirely in the browser. Retrieving
 * the ciphertext consumes one view, so the user is warned before revealing.
 */
(function () {
    'use strict';

    function t(el, name, fallback) {
        return el.getAttribute('data-t-' + name) || fallback;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var app = document.getElementById('secrets-app');
        if (!app) { return; }

        var statusEl = document.getElementById('secrets-status');
        var actionEl = document.getElementById('secrets-action');
        var resultEl = document.getElementById('secrets-result');
        var outputEl = document.getElementById('secrets-output');
        var revealBtn = document.getElementById('secrets-reveal-btn');
        var passWrap = document.getElementById('secrets-pass-wrap');
        var passInput = document.getElementById('secrets-pass');
        var copyBtn = document.getElementById('secrets-copy-btn');
        var metaEl = document.getElementById('secrets-meta');

        var iterations = parseInt(app.getAttribute('data-iterations'), 10) || 310000;
        var peekUrl = app.getAttribute('data-peek-url');
        var consumeUrl = app.getAttribute('data-consume-url');

        function show(el) { el.style.display = ''; }
        function hide(el) { el.style.display = 'none'; }
        function setStatus(msg, cls) {
            statusEl.textContent = msg;
            statusEl.className = 'secrets-status ' + (cls || '');
        }

        if (!window.SecretsCrypto || !SecretsCrypto.supported()) {
            setStatus(t(app, 'unsupported', 'Your browser does not support the required cryptography.'), 'is-error');
            return;
        }

        var keyB64u = (window.location.hash || '').replace(/^#/, '').trim();
        if (!keyB64u) {
            setStatus(t(app, 'missing_key', 'This link is incomplete — the decryption key is missing.'), 'is-error');
            return;
        }

        // Step 1 — non-consuming metadata.
        fetch(peekUrl, { method: 'GET', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (meta) {
                if (!meta || !meta.alive) {
                    setStatus(t(app, 'gone', 'This secret is no longer available. It may have been viewed, or it expired.'), 'is-error');
                    return;
                }
                setStatus(t(app, 'ready', 'A secret is waiting for you.'), 'is-ready');
                if (meta.passphrase_protected) { show(passWrap); }
                if (typeof meta.views_left !== 'undefined' && metaEl) {
                    metaEl.textContent = t(app, 'views_left', 'Views remaining:') + ' ' + meta.views_left;
                }
                show(actionEl);
            })
            .catch(function () {
                setStatus(t(app, 'error', 'Something went wrong. Please try again.'), 'is-error');
            });

        // Step 2 — explicit reveal (consumes a view).
        revealBtn.addEventListener('click', function () {
            revealBtn.disabled = true;
            setStatus(t(app, 'decrypting', 'Decrypting…'), '');

            var passphrase = passInput ? passInput.value : '';

            fetch(consumeUrl, { method: 'POST', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || data.status !== 'ok') {
                        setStatus(t(app, 'gone', 'This secret is no longer available.'), 'is-error');
                        return Promise.reject('gone');
                    }
                    var keyBytes = SecretsCrypto.b64uDecode(keyB64u);
                    return SecretsCrypto.decrypt(data.ciphertext, keyBytes, data.salt, data.iv, passphrase, iterations);
                })
                .then(function (plaintext) {
                    outputEl.value = plaintext;
                    hide(actionEl);
                    show(resultEl);
                    setStatus(t(app, 'destroyed', 'This secret has now been destroyed. Save it somewhere safe.'), 'is-ok');
                })
                .catch(function (err) {
                    if (err === 'gone') { return; }
                    // Decryption failure — almost always a wrong passphrase. The
                    // view was already consumed, so make that explicit.
                    setStatus(t(app, 'wrong', 'Could not decrypt. The passphrase may be wrong — and this attempt used up a view.'), 'is-error');
                    revealBtn.disabled = false;
                });
        });

        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                outputEl.select();
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(outputEl.value).then(function () {
                        copyBtn.textContent = t(app, 'copied', 'Copied');
                    });
                } else {
                    document.execCommand('copy');
                    copyBtn.textContent = t(app, 'copied', 'Copied');
                }
            });
        }
    });
})();
