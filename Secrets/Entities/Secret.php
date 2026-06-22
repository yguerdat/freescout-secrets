<?php

namespace Modules\Secrets\Entities;

use Illuminate\Database\Eloquent\Model;

class Secret extends Model
{
    const DIRECTION_OUTBOUND = 'outbound';
    const DIRECTION_INBOUND  = 'inbound';

    protected $table = 'secrets';

    // The primary key is a non-incrementing random string.
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'direction',
        'ciphertext',
        'salt',
        'iv',
        'wrapped_key',
        'passphrase_protected',
        'conversation_id',
        'max_views',
        'views',
        'expires_at',
        'burned_at',
        'created_by',
        'created_ip_hash',
    ];

    protected $casts = [
        'passphrase_protected' => 'boolean',
        'expires_at'           => 'datetime',
        'burned_at'            => 'datetime',
        'max_views'            => 'integer',
        'views'                => 'integer',
    ];

    /**
     * A secret is alive when it still holds ciphertext, has not been burned,
     * has not expired and has views left.
     */
    public function isAlive(): bool
    {
        return $this->ciphertext !== null
            && $this->burned_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture()
            && $this->views < $this->max_views;
    }

    public function viewsLeft(): int
    {
        return max(0, (int) $this->max_views - (int) $this->views);
    }

    /**
     * Permanently destroy the payload. The row is kept (with NULL ciphertext)
     * until the purge cron removes it, so the reveal page can explain that the
     * secret was already consumed instead of showing a bare 404.
     */
    public function burn(): void
    {
        $this->ciphertext = null;
        $this->wrapped_key = null;
        $this->salt = null;
        $this->iv = null;
        $this->burned_at = now();
        $this->save();
    }
}
