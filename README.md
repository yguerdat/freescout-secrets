# Secrets — FreeScout Module

Share **one-time secrets** (passwords, API keys, configuration) with your customers — and let them send secrets back to you — without ever putting them in clear text in an e-mail.

![License](https://img.shields.io/badge/license-AGPL--3.0-blue)
![FreeScout](https://img.shields.io/badge/FreeScout-1.8.58+-green)

Built and maintained by Yannick Guerdat, open-sourced for the FreeScout community.

## Why

E-mailing a password is insecure: it lives forever in mailboxes, backups and logs. This module replaces that with **end-to-end encrypted, self-destructing links**:

- Paste a secret → get a link that works for *N* days and *N* views (both configurable).
- The secret is **encrypted in the browser**. The decryption key lives only in the URL fragment (`#…`), which browsers never send to the server. The server stores an **unreadable blob** — a database dump reveals nothing.
- Optional **passphrase** for a second factor, deliverable to the customer by **SMS** (via [SMSeagle](https://www.smseagle.eu/)) over a separate channel.
- Customers can **send secrets to you** through a public form. It opens a support ticket with a **burn-after-read reveal button** — the secret is never written in clear into the ticket or any notification e-mail, and it is destroyed after a configurable number of agent reveals.
- Generate a secret link **without leaving the reply editor** — a lock button in the conversation toolbar creates the link and inserts it into your message.
- **Audit and revoke** every secret from a management page: see view counts, status and linked ticket, and destroy any secret on demand.

## Security model

| Direction | Model |
|-----------|-------|
| **Agent → customer** (outbound) | **Zero-knowledge.** AES-256-GCM in the browser, key only in the URL fragment. The server cannot read the secret. |
| **Customer → agent** (inbound) | Client-side AES-256-GCM; the random content key is wrapped with the module's **RSA-4096** public key. The private key is **sealed with the app's `APP_KEY`** and only unsealed in memory when an agent clicks *Reveal*. An optional passphrase (mixed into key derivation client-side) is **never** sent to the server. |

Because an agent must be able to read an inbound secret, an inbound secret *without* a passphrase is — by design — decryptable by someone holding both `APP_KEY` and a database dump. Mitigations: sealed private key, burn-after-read, short retention, and a passphrase for anything highly sensitive. **Outbound secrets are not affected** — they remain zero-knowledge.

Additional hardening:

- Strict **Content-Security-Policy** (no inline scripts), `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, `no-store` caching, HSTS on the public pages.
- Unguessable 144-bit identifiers; per-IP rate limiting; honeypot on the intake form.
- **Atomic, row-locked** view consumption — concurrent requests can never both read the final view.
- Burn = the ciphertext is set to `NULL`, not merely flagged. An hourly cron hard-deletes expired rows.
- Secrets are never written to logs. The creator IP is stored only as an HMAC.

## Requirements

- [FreeScout](https://github.com/freescout-help-desk/freescout) ≥ 1.8.58
- PHP with the OpenSSL extension (standard)
- A modern browser (WebCrypto). Optional: an [SMSeagle](https://www.smseagle.eu/) gateway for SMS passphrase delivery.

A **dedicated sub-domain** for the public pages (e.g. `secrets.example.com`) is strongly recommended for isolation. Point it at the same FreeScout app; the module routes `/secrets/s/…`, `/secrets/new` and `/api/secrets/…`.

## Installation

1. Copy the `Secrets` folder into your FreeScout `Modules/` directory:
   ```bash
   cp -r Secrets /path/to/freescout/Modules/
   ```
2. Run the migration and build the assets:
   ```bash
   cd /path/to/freescout
   php artisan migrate
   php artisan freescout:module-build
   ```
3. Go to **Manage → Modules** and activate **Secrets**.
4. Open **Manage → Secrets** and set the **public base URL**, default expiry/views, the intake mailbox and (optionally) your SMSeagle credentials.

## Usage

**Send a secret to a customer** — click the 🔒 in the top navbar (*Send a secret*), paste the value, choose expiry/views, optionally set a passphrase (and SMS it), then copy the generated link and send it to the customer.

**…or straight from the reply** — while writing a reply, click the 🔒 button in the editor toolbar. Create the secret in the dialog and the link is inserted into your message, no context switch.

**Receive a secret from a customer** — share `https://<public-base-url>/secrets/new`. When a customer submits, a ticket opens in the configured mailbox with a *Reveal secret* panel; click it to decrypt the value in your browser. The panel shows how many reveals remain before the secret is destroyed.

**Manage & revoke** — **Manage → Secrets → Sent secrets** lists every secret with its status, view count, expiry and linked ticket. Revoke any of them to destroy the payload immediately. Outbound secrets stay zero-knowledge — the page shows metadata only, never the content.

## SMS (SMSeagle)

Configured under **Manage → Secrets → SMS**. Uses the SMSeagle HTTP API v2 (`POST /api/v2/messages/sms`, `access-token` header). Only the passphrase is sent — never the link, so the two factors travel on separate channels.

## License

[AGPL-3.0](LICENSE) © Yannick Guerdat.
