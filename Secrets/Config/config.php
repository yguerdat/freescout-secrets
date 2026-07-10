<?php

return [
    'name' => 'Secrets',

    // Public base URL where the reveal page and the customer intake form are
    // served. A dedicated sub-domain is strongly recommended (isolation + a
    // smaller attack surface). Overridable in the admin settings.
    'public_base_url' => env('SECRETS_PUBLIC_URL', 'https://secrets.example.com'),

    // Defaults for new outgoing secrets (admin-configurable).
    'default_ttl_hours' => 168,   // 7 days
    'default_max_views' => 1,     // burn after first read

    // How many times an agent may reveal an inbound (customer-submitted) secret
    // before it is destroyed. Burn-after-read applies here too.
    'inbound_max_views' => 3,

    // Hard ceilings — a secret can never live longer / be opened more than this.
    'max_ttl_hours'  => 720,      // 30 days
    'max_views_cap'  => 100,

    // Maximum decrypted payload size accepted, in bytes (anti-DoS). The
    // ciphertext is base64 and slightly larger; the request limit allows for it.
    'max_secret_bytes' => 262144, // 256 KB

    // Client-side PBKDF2 work factor (kept in sync with the JS).
    'pbkdf2_iterations' => 310000,

    // Per-IP rate limits (requests / minute).
    'rate_limit_create' => 20,
    'rate_limit_reveal' => 60,

    // Whether the public customer -> agent intake form is enabled by default.
    'inbound_enabled' => true,
];
