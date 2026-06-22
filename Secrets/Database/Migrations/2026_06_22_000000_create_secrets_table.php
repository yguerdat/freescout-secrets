<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSecretsTable extends Migration
{
    public function up()
    {
        Schema::create('secrets', function (Blueprint $table) {
            // Unguessable public identifier (base64url, >=128 bits of entropy).
            // This is the only handle to a secret; it appears in the URL.
            $table->string('id', 32)->primary();

            // outbound = agent -> customer (zero-knowledge, key only in the URL
            // fragment). inbound = customer -> agent (key wrapped to the module
            // RSA public key so an agent can reveal it from the ticket).
            $table->string('direction', 10)->index();

            // AES-256-GCM ciphertext (iv prefixed client-side, then base64). Set
            // to NULL the moment the secret is burned — the bytes are gone.
            $table->longText('ciphertext')->nullable();

            // Non-secret crypto parameters needed to derive the key client-side.
            $table->string('salt', 64)->nullable();   // base64, PBKDF2 salt
            $table->string('iv', 32)->nullable();      // base64, AES-GCM IV

            // inbound only: the random content key, RSA-OAEP encrypted to the
            // module public key. The matching private key is sealed with APP_KEY.
            $table->longText('wrapped_key')->nullable();

            // Whether an extra passphrase is mixed into the key derivation. The
            // passphrase itself is never sent to or stored on the server.
            $table->boolean('passphrase_protected')->default(false);

            // Linked FreeScout conversation (set for inbound, optional audit for
            // outbound).
            $table->unsignedInteger('conversation_id')->nullable()->index();

            $table->unsignedInteger('max_views')->default(1);
            $table->unsignedInteger('views')->default(0);

            $table->timestamp('expires_at')->index();
            $table->timestamp('burned_at')->nullable();

            // Author (agent) for outbound; NULL for anonymous inbound.
            $table->unsignedInteger('created_by')->nullable();

            // HMAC of the creator IP (abuse control without storing PII in clear).
            $table->string('created_ip_hash', 64)->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('secrets');
    }
}
