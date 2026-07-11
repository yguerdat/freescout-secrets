# Changelog

All notable changes to this module are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/), and the project adheres to
[Semantic Versioning](https://semver.org/).

## [1.1.2] — 2026-07-11

### Fixed
- **Reply composer button now actually appears.** Its initializer was defined
  but never invoked, so the lock button never rendered. It is now wired up and
  uses a `MutationObserver` to attach to the Summernote toolbar reliably as the
  editor is (re)created — available both when replying to a conversation and on
  the new-conversation form. Each front-end initializer is isolated so one
  failing can't block the others.

## [1.1.1] — 2026-07-10

### Security
- **Management page access control.** The *Sent secrets* page and its revoke
  action now enforce ownership: an agent sees and can revoke only the secrets
  they created, while admins retain the org-wide view. Fixes an IDOR where any
  authenticated agent could revoke another agent's secret, and an information
  disclosure of all secrets' metadata.

## [1.1.0] — 2026-07-10

### Added
- **Reply composer** — a lock button in the conversation reply toolbar creates a
  secret and inserts the link straight into the message, no context switch
  (closes [#1](https://github.com/yguerdat/freescout-secrets/issues/1)).
- **Management page** (*Manage → Secrets → Sent secrets*) — audit every secret
  with its status, view count, expiry and linked ticket, and revoke (destroy)
  any of them on demand.
- **Configurable inbound reveals** — a setting controls how many times an agent
  may reveal a customer-submitted secret before it is destroyed.
- **Favicon** on the public pages.

### Fixed
- **Inbound secrets are now burn-after-read.** Previously a customer-submitted
  secret could be revealed indefinitely; it is now destroyed after the
  configured number of reveals, and the reveal panel shows how many remain.
- **Button hover contrast** on the public pages — text stays legible on hover.
- The in-ticket reveal panel is explicitly guarded to agents only (never
  rendered in a customer portal).

## [1.0.0] — 2026-06-22

### Added
- Initial release: zero-knowledge, end-to-end encrypted one-time secret sharing.
- Outbound agent→customer links (AES-256-GCM, key in the URL fragment).
- Inbound customer→agent intake (RSA-wrapped key, burn-after-read ticket panel).
- Optional passphrase with SMSeagle SMS delivery.
- Strict CSP, rate limiting, atomic burn, hourly purge cron.
- FR / EN / DE, configurable branding (name, tagline, accent colour, logo).
