<?php

namespace Modules\Secrets\Services;

use Illuminate\Support\Facades\DB;
use Modules\Secrets\Entities\Secret;

/**
 * Core domain logic for storing and consuming secrets.
 *
 * The service never receives plaintext: callers always hand over ciphertext and
 * non-secret crypto parameters produced in the browser.
 */
class SecretService
{
    /* ----------------------------------------------------------------- *
     | Settings helpers
     * ----------------------------------------------------------------- */

    public function publicBaseUrl(): string
    {
        // Literal fallbacks make the module robust to FreeScout caching config
        // (which skips a module's mergeConfigFrom).
        return rtrim((string) (\Option::get('secrets.public_base_url')
            ?: config('secrets.public_base_url', config('app.url'))), '/');
    }

    public function defaultTtlHours(): int
    {
        return (int) (\Option::get('secrets.default_ttl_hours') ?: config('secrets.default_ttl_hours', 168));
    }

    public function defaultMaxViews(): int
    {
        return (int) (\Option::get('secrets.default_max_views') ?: config('secrets.default_max_views', 1));
    }

    public function maxTtlHours(): int
    {
        return (int) config('secrets.max_ttl_hours', 720);
    }

    public function maxViewsCap(): int
    {
        return (int) config('secrets.max_views_cap', 100);
    }

    public function inboundEnabled(): bool
    {
        // FreeScout's Option::get() defaults to false, so pass an explicit
        // default of true: enabled until an admin turns it off.
        return (bool) \Option::get('secrets.inbound_enabled', true);
    }

    /**
     * Clamp caller-supplied limits to the configured ceilings.
     */
    public function clampTtlHours($hours): int
    {
        $hours = (int) $hours;
        if ($hours < 1) {
            $hours = $this->defaultTtlHours();
        }
        return min($hours, $this->maxTtlHours());
    }

    public function clampMaxViews($views): int
    {
        $views = (int) $views;
        if ($views < 1) {
            $views = 1;
        }
        return min($views, $this->maxViewsCap());
    }

    /* ----------------------------------------------------------------- *
     | Creation
     * ----------------------------------------------------------------- */

    /**
     * Store a new secret. $data holds only ciphertext + non-secret parameters.
     */
    public function store(array $data): Secret
    {
        $secret = new Secret();
        $secret->id = Crypto::newId();
        $secret->direction = $data['direction'];
        $secret->ciphertext = $data['ciphertext'];
        $secret->salt = $data['salt'] ?? null;
        $secret->iv = $data['iv'] ?? null;
        $secret->wrapped_key = $data['wrapped_key'] ?? null;
        $secret->passphrase_protected = !empty($data['passphrase_protected']);
        $secret->conversation_id = $data['conversation_id'] ?? null;
        $secret->max_views = $this->clampMaxViews($data['max_views'] ?? $this->defaultMaxViews());
        $secret->views = 0;
        $secret->expires_at = now()->addHours($this->clampTtlHours($data['ttl_hours'] ?? $this->defaultTtlHours()));
        $secret->created_by = $data['created_by'] ?? null;
        $secret->created_ip_hash = Crypto::hashIp($data['ip'] ?? null);
        $secret->save();

        return $secret;
    }

    /* ----------------------------------------------------------------- *
     | Retrieval
     * ----------------------------------------------------------------- */

    /**
     * Fetch a secret without consuming a view (used to render the reveal page
     * metadata: does it still exist, is a passphrase required, how many views
     * are left).
     */
    public function peek(string $id): ?Secret
    {
        return Secret::find($id);
    }

    /**
     * Atomically consume one view and return the ciphertext payload.
     *
     * Returns an array describing the outcome:
     *   ['status' => 'ok', 'secret' => Secret]      payload usable
     *   ['status' => 'gone']                         expired / burned / not found
     *
     * The conditional, row-locked update guarantees that two concurrent
     * requests can never both read the final view.
     */
    public function consume(string $id): array
    {
        return DB::transaction(function () use ($id) {
            /** @var Secret|null $secret */
            $secret = Secret::where('id', $id)->lockForUpdate()->first();

            if (!$secret || !$secret->isAlive()) {
                return ['status' => 'gone'];
            }

            // Snapshot the payload before we possibly burn it.
            $payload = [
                'ciphertext'           => $secret->ciphertext,
                'salt'                 => $secret->salt,
                'iv'                   => $secret->iv,
                'wrapped_key'          => $secret->wrapped_key,
                'passphrase_protected' => $secret->passphrase_protected,
            ];

            $secret->views = $secret->views + 1;

            if ($secret->views >= $secret->max_views) {
                // Last allowed read — destroy the payload in the same tx.
                $secret->ciphertext = null;
                $secret->wrapped_key = null;
                $secret->salt = null;
                $secret->iv = null;
                $secret->burned_at = now();
            }

            $secret->save();

            return ['status' => 'ok', 'secret' => $secret, 'payload' => $payload];
        });
    }

    /* ----------------------------------------------------------------- *
     | Maintenance
     * ----------------------------------------------------------------- */

    /**
     * Hard-delete expired or burned rows. Run from the scheduler. Returns the
     * number of rows removed.
     */
    public function purge(): int
    {
        return Secret::where('expires_at', '<', now())
            ->orWhereNotNull('burned_at')
            ->delete();
    }
}
