/*
 * Secrets — browser cryptography (zero-knowledge).
 *
 * AES-256-GCM content encryption with a key derived via PBKDF2-SHA256 from a
 * random 256-bit key (carried in the URL fragment, never sent to the server)
 * optionally mixed with a user passphrase. For the customer intake flow, the
 * random content key is additionally wrapped to the module RSA public key
 * (RSA-OAEP, SHA-1 to match PHP's OPENSSL_PKCS1_OAEP_PADDING).
 *
 * No secret value is ever logged or stored outside the page's memory.
 */
window.SecretsCrypto = (function () {
    'use strict';

    var enc = new TextEncoder();
    var dec = new TextDecoder();

    function b64uEncode(bytes) {
        var bin = '';
        var chunk = 0x8000;
        for (var i = 0; i < bytes.length; i += chunk) {
            bin += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
        }
        return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function b64uDecode(str) {
        str = String(str).replace(/-/g, '+').replace(/_/g, '/');
        while (str.length % 4) { str += '='; }
        var bin = atob(str);
        var out = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) { out[i] = bin.charCodeAt(i); }
        return out;
    }

    function deriveKey(rawKeyBytes, passphrase, salt, iterations) {
        var pass = passphrase ? enc.encode(passphrase) : new Uint8Array(0);
        var material = new Uint8Array(rawKeyBytes.length + pass.length);
        material.set(rawKeyBytes, 0);
        material.set(pass, rawKeyBytes.length);

        return crypto.subtle.importKey('raw', material, 'PBKDF2', false, ['deriveKey'])
            .then(function (baseKey) {
                return crypto.subtle.deriveKey(
                    { name: 'PBKDF2', salt: salt, iterations: iterations, hash: 'SHA-256' },
                    baseKey,
                    { name: 'AES-GCM', length: 256 },
                    false,
                    ['encrypt', 'decrypt']
                );
            });
    }

    function encrypt(plaintext, passphrase, iterations) {
        var rawKey = crypto.getRandomValues(new Uint8Array(32));
        var salt = crypto.getRandomValues(new Uint8Array(16));
        var iv = crypto.getRandomValues(new Uint8Array(12));

        return deriveKey(rawKey, passphrase, salt, iterations).then(function (key) {
            return crypto.subtle.encrypt({ name: 'AES-GCM', iv: iv }, key, enc.encode(plaintext));
        }).then(function (ct) {
            return {
                rawKey: rawKey,
                keyB64u: b64uEncode(rawKey),
                saltB64u: b64uEncode(salt),
                ivB64u: b64uEncode(iv),
                ciphertextB64u: b64uEncode(new Uint8Array(ct))
            };
        });
    }

    function decrypt(ciphertextB64u, keyBytes, saltB64u, ivB64u, passphrase, iterations) {
        var salt = b64uDecode(saltB64u);
        var iv = b64uDecode(ivB64u);
        var ct = b64uDecode(ciphertextB64u);

        return deriveKey(keyBytes, passphrase, salt, iterations).then(function (key) {
            return crypto.subtle.decrypt({ name: 'AES-GCM', iv: iv }, key, ct);
        }).then(function (pt) {
            return dec.decode(pt);
        });
    }

    function importRsaPublicKey(pem) {
        var b64 = pem.replace(/-----[^-]+-----/g, '').replace(/\s+/g, '');
        var der = b64uDecode(b64);
        return crypto.subtle.importKey('spki', der, { name: 'RSA-OAEP', hash: 'SHA-1' }, false, ['encrypt']);
    }

    function wrapKey(rawKeyBytes, rsaPublicKey) {
        return crypto.subtle.encrypt({ name: 'RSA-OAEP' }, rsaPublicKey, rawKeyBytes)
            .then(function (wrapped) { return b64uEncode(new Uint8Array(wrapped)); });
    }

    function supported() {
        return !!(window.crypto && window.crypto.subtle && window.TextEncoder);
    }

    return {
        b64uEncode: b64uEncode,
        b64uDecode: b64uDecode,
        encrypt: encrypt,
        decrypt: decrypt,
        importRsaPublicKey: importRsaPublicKey,
        wrapKey: wrapKey,
        supported: supported
    };
})();
