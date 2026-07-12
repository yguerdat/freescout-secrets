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
        var n = alphabet.length;
        // Rejection sampling: discard the biased tail so every character is
        // equally likely (plain `byte % n` would over-represent the first
        // 256 % n characters).
        var max = 256 - (256 % n);
        var chars = [];
        var buf = new Uint8Array(1);
        while (chars.length < 20) {
            crypto.getRandomValues(buf);
            if (buf[0] >= max) { continue; }
            chars.push(alphabet[buf[0] % n]);
        }
        var out = '';
        for (var i = 0; i < chars.length; i++) {
            out += chars[i];
            if (i % 4 === 3 && i < chars.length - 1) { out += '-'; }
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

                var viewsLeft = null;
                var viewsEl = row.querySelector('.secrets-inbound-views');

                fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data || data.status !== 'ok') {
                            statusEl.textContent = t(panel, 'gone', 'This secret is no longer available.');
                            if (viewsEl) { viewsEl.style.display = 'none'; }
                            btn.style.display = 'none';
                            return Promise.reject('gone');
                        }
                        viewsLeft = (typeof data.views_left === 'number') ? data.views_left : null;
                        var keyBytes = SecretsCrypto.b64uDecode(data.key);
                        return SecretsCrypto.decrypt(data.ciphertext, keyBytes, data.salt, data.iv, passphrase, iterations);
                    })
                    .then(function (plaintext) {
                        out.value = plaintext;
                        out.style.display = '';
                        // Reflect the burn-after-read state.
                        if (viewsEl && viewsLeft !== null) {
                            viewsEl.textContent = viewsLeft + ' ' + t(panel, 'views_left', 'reveals left');
                        }
                        if (viewsLeft === 0) {
                            btn.style.display = 'none';
                            statusEl.textContent = t(panel, 'burned', 'Destroyed — this secret can no longer be revealed.');
                        } else {
                            btn.disabled = false;
                            statusEl.textContent = t(panel, 'revealed', 'Revealed. Copy it now — it will not be shown again.');
                        }
                    })
                    .catch(function (err) {
                        if (err === 'gone') { return; }
                        statusEl.textContent = t(panel, 'wrong', 'Could not decrypt — the passphrase may be wrong.');
                        btn.disabled = false;
                    });
            });
        });
    }

    /* --------------------------------------------------------------- C
     | Insert a secret link straight into the conversation reply (issue #1).
     */
    function initCompose() {
        var cfg = document.getElementById('secrets-compose');
        var $ = window.jQuery || window.$;
        if (!cfg || !window.SecretsCrypto || !$) { return; }

        var csrf = cfg.getAttribute('data-csrf');
        var iterations = parseInt(cfg.getAttribute('data-iterations'), 10) || 310000;
        var storeUrl = cfg.getAttribute('data-store-url');
        var smsUrl = cfg.getAttribute('data-sms-url');
        var smsConfigured = cfg.getAttribute('data-sms-configured') === '1';
        var btnLabel = cfg.getAttribute('data-btn-label') || 'Insert a secret link';
        var linkText = cfg.getAttribute('data-link-text') || 'Open the secure link';

        function escapeHtml(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // Move the modal to <body> so it is never trapped in an overflow container.
        var modal = document.getElementById('secrets-compose-modal');
        if (modal && modal.parentNode !== document.body) { document.body.appendChild(modal); }

        var secretEl = document.getElementById('sccm-secret');
        var ttlEl = document.getElementById('sccm-ttl');
        var viewsEl = document.getElementById('sccm-views');
        var passEl = document.getElementById('sccm-pass');
        var genBtn = document.getElementById('sccm-gen');
        var createBtn = document.getElementById('sccm-create');
        var statusEl = document.getElementById('sccm-status');
        var smsWrap = document.getElementById('sccm-sms-wrap');
        var smsPhone = document.getElementById('sccm-sms-phone');
        var smsBtn = document.getElementById('sccm-sms-btn');
        var smsStatus = document.getElementById('sccm-sms-status');

        var activeTextarea = null; // Summernote element to insert into
        var pendingHtml = null;

        function setStatus(msg, clsName) { statusEl.textContent = msg || ''; statusEl.className = 'secrets-status ' + (clsName || ''); }

        if (genBtn) { genBtn.addEventListener('click', function () { passEl.value = randomPassphrase(); toggleSms(); }); }
        if (passEl) { passEl.addEventListener('input', toggleSms); }
        function toggleSms() { if (smsWrap) { smsWrap.style.display = (smsConfigured && passEl.value) ? '' : 'none'; } }

        // Inject a toolbar button into each Summernote editor as it appears.
        // Editors are created/re-created lazily, so we keep watching the DOM.
        function injectButtons() {
            var toolbars = document.querySelectorAll('.note-toolbar');
            Array.prototype.forEach.call(toolbars, function (toolbar) {
                if (toolbar.getAttribute('data-secrets-injected')) { return; }
                toolbar.setAttribute('data-secrets-injected', '1');

                var noteEditor = toolbar.closest('.note-editor');
                var textarea = noteEditor ? noteEditor.previousElementSibling : null;

                var group = document.createElement('div');
                group.className = 'note-btn-group btn-group';
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'note-btn btn btn-default btn-sm';
                b.setAttribute('tabindex', '-1');
                b.title = btnLabel;
                b.innerHTML = '<i class="glyphicon glyphicon-lock"></i>';
                // Keep the caret where the agent was typing when they click us.
                b.addEventListener('mousedown', function (e) { e.preventDefault(); });
                b.addEventListener('click', function (e) {
                    e.preventDefault();
                    activeTextarea = textarea;
                    pendingHtml = null;
                    // Remember the current caret position inside the editor.
                    try { $(textarea).summernote('saveRange'); } catch (err) {}
                    secretEl.value = ''; passEl.value = ''; setStatus('', ''); toggleSms();
                    createBtn.disabled = false;
                    $(modal).modal('show');
                });
                group.appendChild(b);
                // Prepend so the button is always visible at the toolbar's start.
                if (toolbar.firstChild) { toolbar.insertBefore(group, toolbar.firstChild); }
                else { toolbar.appendChild(group); }
            });
        }

        injectButtons();
        if (window.MutationObserver) {
            var obs = new MutationObserver(function () { injectButtons(); });
            obs.observe(document.body, { childList: true, subtree: true });
        } else {
            var tries = 0;
            var poll = setInterval(function () { injectButtons(); if (++tries > 120) { clearInterval(poll); } }, 500);
        }

        // Insert the link once the modal is fully closed (focus restored).
        $(modal).on('hidden.bs.modal', function () {
            if (!pendingHtml) { return; }
            var html = pendingHtml; pendingHtml = null;
            try {
                if (activeTextarea && $(activeTextarea).summernote) {
                    // Restore the caret we saved and insert there (not at the top).
                    try { $(activeTextarea).summernote('restoreRange'); } catch (e) {}
                    $(activeTextarea).summernote('focus');
                    $(activeTextarea).summernote('pasteHTML', html);
                } else {
                    throw new Error('no summernote');
                }
            } catch (err) {
                // Fallback: append to the editable and sync.
                var editable = activeTextarea && activeTextarea.parentNode
                    ? activeTextarea.parentNode.querySelector('.note-editable') : null;
                if (editable) {
                    editable.focus();
                    document.execCommand('insertHTML', false, html);
                    editable.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }
        });

        createBtn.addEventListener('click', function () {
            var secret = secretEl.value;
            if (!secret) { setStatus(t(cfg, 'required', 'Enter the secret to share.'), 'is-error'); return; }
            createBtn.disabled = true;
            setStatus(t(cfg, 'encrypting', 'Encrypting…'), '');
            var passphrase = passEl.value;

            SecretsCrypto.encrypt(secret, passphrase, iterations).then(function (enc) {
                return postJson(storeUrl, csrf, {
                    ciphertext: enc.ciphertextB64u, salt: enc.saltB64u, iv: enc.ivB64u,
                    passphrase_protected: passphrase ? '1' : '0',
                    ttl_hours: ttlEl.value, max_views: viewsEl.value
                }).then(function (res) {
                    if (!res.ok || !res.j || !res.j.url) { throw new Error('server'); }
                    // The link contains only the configured base URL + base64url
                    // id/key — no user HTML — so it is safe to insert as markup.
                    var link = res.j.url + '#' + enc.keyB64u;
                    var safe = link.replace(/"/g, '%22');
                    // Short, localized, inline anchor text — not the raw URL,
                    // which wraps and breaks in many mail clients.
                    pendingHtml = '<a href="' + safe + '">🔒 ' + escapeHtml(linkText) + '</a>';
                    secretEl.value = '';
                    setStatus(t(cfg, 'inserted', 'Secret link inserted into your reply.'), 'is-ok');
                    // Insertion happens on the modal's hidden event (focus restored).
                    $(modal).modal('hide');
                });
            }).catch(function () {
                setStatus(t(cfg, 'error', 'Could not create the secret. Please try again.'), 'is-error');
                createBtn.disabled = false;
            });
        });

        if (smsBtn) {
            smsBtn.addEventListener('click', function () {
                if (!smsPhone.value || !passEl.value) { return; }
                smsBtn.disabled = true;
                smsStatus.textContent = t(cfg, 'sms_sending', 'Sending…');
                postJson(smsUrl, csrf, { to: smsPhone.value, passphrase: passEl.value }).then(function (res) {
                    if (res.ok && res.j && res.j.status === 'ok') { smsStatus.textContent = t(cfg, 'sms_sent', 'Passphrase sent by SMS.'); }
                    else { smsStatus.textContent = (res.j && res.j.message) || t(cfg, 'sms_error', 'SMS failed.'); smsBtn.disabled = false; }
                }).catch(function () { smsStatus.textContent = t(cfg, 'sms_error', 'SMS failed.'); smsBtn.disabled = false; });
            });
        }
    }

    function initConfirms() {
        var forms = document.querySelectorAll('form.secrets-confirm');
        Array.prototype.forEach.call(forms, function (form) {
            form.addEventListener('submit', function (e) {
                var msg = form.getAttribute('data-confirm') || 'Are you sure?';
                if (!window.confirm(msg)) { e.preventDefault(); }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Isolate each initializer so one failing never blocks the others.
        [initCreate, initInboundPanel, initCompose, initConfirms].forEach(function (fn) {
            try { fn(); } catch (e) { try { console.error('[secrets] init error', e); } catch (e2) {} }
        });
    });
})();
