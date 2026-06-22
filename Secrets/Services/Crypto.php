<?php

namespace Modules\Secrets\Services;

use Illuminate\Support\Facades\Crypt;

/**
 * Server-side cryptographic helpers.
 *
 * The module never sees the plaintext of an *outbound* secret: that is pure
 * zero-knowledge, the key lives only in the URL fragment.
 *
 * For *inbound* secrets (customer -> agent) the module owns an RSA key pair.
 * The customer's browser encrypts the random content key to the public key
 * (RSA-OAEP). The private key is stored sealed with the Laravel APP_KEY and is
 * only ever unsealed in memory when an agent explicitly reveals the secret.
 *
 * Interop note: PHP's OPENSSL_PKCS1_OAEP_PADDING uses MGF1-SHA1, so the browser
 * side MUST import the public key with hash "SHA-1" for RSA-OAEP. The OAEP hash
 * is not the security-relevant parameter here (the 4096-bit modulus is).
 */
class Crypto
{
    const OPT_PUBLIC  = 'secrets.rsa_public';
    const OPT_PRIVATE = 'secrets.rsa_private_sealed';

    /**
     * Generate a URL-safe, unguessable identifier (24 chars base64url ~ 144 bits).
     */
    public static function newId(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }

    /**
     * HMAC an IP address with the APP_KEY so abuse limits can be enforced
     * without storing the address in clear.
     */
    public static function hashIp(?string $ip): ?string
    {
        if (empty($ip)) {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    /**
     * Ensure the module RSA key pair exists, generating it on first use.
     * Returns the PEM-encoded public key.
     */
    public function ensureKeypair(): string
    {
        $public = \Option::get(self::OPT_PUBLIC);
        $sealed = \Option::get(self::OPT_PRIVATE);

        if (!empty($public) && !empty($sealed)) {
            return $public;
        }

        $res = openssl_pkey_new([
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($res === false) {
            throw new \RuntimeException('Secrets: unable to generate RSA key pair: ' . openssl_error_string());
        }

        openssl_pkey_export($res, $privatePem);
        $details = openssl_pkey_get_details($res);
        $publicPem = $details['key'];

        \Option::set(self::OPT_PUBLIC, $publicPem);
        // Sealed at rest with APP_KEY (AES-256 via Laravel's encrypter).
        \Option::set(self::OPT_PRIVATE, Crypt::encryptString($privatePem));

        return $publicPem;
    }

    public function publicKey(): string
    {
        return $this->ensureKeypair();
    }

    /**
     * Unseal the private key and RSA-OAEP decrypt a wrapped content key.
     * Returns the raw key bytes. Only called when an agent reveals an inbound
     * secret.
     */
    public function unwrapKey(string $wrappedKeyB64): string
    {
        $sealed = \Option::get(self::OPT_PRIVATE);
        if (empty($sealed)) {
            throw new \RuntimeException('Secrets: module key pair is missing.');
        }

        $privatePem = Crypt::decryptString($sealed);
        $key = openssl_pkey_get_private($privatePem);
        if ($key === false) {
            throw new \RuntimeException('Secrets: cannot load private key.');
        }

        $wrapped = base64_decode(strtr($wrappedKeyB64, '-_', '+/'), true);
        if ($wrapped === false) {
            throw new \RuntimeException('Secrets: malformed wrapped key.');
        }

        $out = '';
        $ok = openssl_private_decrypt($wrapped, $out, $key, OPENSSL_PKCS1_OAEP_PADDING);
        if (!$ok) {
            throw new \RuntimeException('Secrets: failed to unwrap content key: ' . openssl_error_string());
        }

        return $out;
    }
}
