<?php

namespace Modules\Secrets\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Secrets\Entities\Secret;
use Modules\Secrets\Services\Crypto;
use Modules\Secrets\Services\SecretService;

/**
 * Public, unauthenticated endpoints: the reveal page, the customer intake form
 * and the stateless JSON API behind them. No plaintext ever transits here.
 */
class PublicController extends Controller
{
    private SecretService $service;

    public function __construct()
    {
        $this->service = new SecretService();
    }

    /* ----------------------------------------------------------------- *
     | Reveal (outbound or inbound-by-customer is agent-only, so this is
     | the recipient-facing reveal for outbound secrets).
     * ----------------------------------------------------------------- */

    public function revealPage($token)
    {
        $id = $token;
        // The page only renders a shell; the ciphertext is fetched and decrypted
        // client-side. Validate the id shape to avoid pointless DB hits.
        return view('secrets::public.reveal', [
            'id'         => $this->sanitizeId($id),
            'iterations' => (int) config('secrets.pbkdf2_iterations', 310000),
        ]);
    }

    public function peek($token)
    {
        $id = $this->sanitizeId($token);
        $secret = $this->service->peek($id);

        if (!$secret || $secret->direction !== Secret::DIRECTION_OUTBOUND) {
            // Inbound secrets are revealed by agents only, never here.
            return $this->json(['alive' => false]);
        }

        return $this->json([
            'alive'                => $secret->isAlive(),
            'passphrase_protected' => (bool) $secret->passphrase_protected,
            'views_left'           => $secret->viewsLeft(),
            'expires_at'           => optional($secret->expires_at)->toIso8601String(),
        ]);
    }

    public function consume(Request $request, $token)
    {
        $id = $this->sanitizeId($token);
        $secret = $this->service->peek($id);

        if (!$secret || $secret->direction !== Secret::DIRECTION_OUTBOUND) {
            return $this->json(['status' => 'gone']);
        }

        $result = $this->service->consume($id);

        if ($result['status'] !== 'ok') {
            return $this->json(['status' => 'gone']);
        }

        $p = $result['payload'];

        return $this->json([
            'status'               => 'ok',
            'ciphertext'           => $p['ciphertext'],
            'salt'                 => $p['salt'],
            'iv'                   => $p['iv'],
            'passphrase_protected' => (bool) $p['passphrase_protected'],
            'views_left'           => $result['secret']->viewsLeft(),
        ]);
    }

    /* ----------------------------------------------------------------- *
     | Inbound intake (customer -> agent)
     * ----------------------------------------------------------------- */

    public function inboundForm()
    {
        if (!$this->service->inboundEnabled()) {
            abort(404);
        }

        return view('secrets::public.inbound', [
            'iterations' => (int) config('secrets.pbkdf2_iterations', 310000),
        ]);
    }

    public function pubkey()
    {
        if (!$this->service->inboundEnabled()) {
            return $this->json(['error' => 'disabled'], 404);
        }

        $crypto = new Crypto();
        return $this->json(['public_key' => $crypto->publicKey()]);
    }

    public function inboundStore(Request $request)
    {
        if (!$this->service->inboundEnabled()) {
            return $this->json(['error' => 'disabled'], 404);
        }

        // Honeypot: bots fill hidden fields.
        if ($request->filled('website')) {
            return $this->json(['status' => 'ok']); // pretend success
        }

        $maxBytes = (int) config('secrets.max_secret_bytes', 262144);
        $data = $request->validate([
            'ciphertext'           => 'required|string|max:' . ($maxBytes * 2),
            'salt'                 => 'required|string|max:128',
            'iv'                   => 'required|string|max:64',
            'wrapped_key'          => 'required|string|max:2048',
            'passphrase_protected' => 'nullable|boolean',
            'email'                => 'required|email|max:191',
            'subject'              => 'nullable|string|max:191',
            'note'                 => 'nullable|string|max:1000',
        ]);

        try {
            $conversation = (new \Modules\Secrets\Services\ConversationFactory())->createForInbound(
                $data['email'],
                $data['subject'] ?? __('Secure information from :name', ['name' => $data['email']]),
                $data['note'] ?? ''
            );
        } catch (\Throwable $e) {
            \Log::error('Secrets: inbound conversation creation failed: ' . $e->getMessage());
            return $this->json(['error' => 'server'], 500);
        }

        $this->service->store([
            'direction'            => Secret::DIRECTION_INBOUND,
            'ciphertext'           => $data['ciphertext'],
            'salt'                 => $data['salt'],
            'iv'                   => $data['iv'],
            'wrapped_key'          => $data['wrapped_key'],
            'passphrase_protected' => !empty($data['passphrase_protected']),
            'conversation_id'      => $conversation->id,
            'max_views'            => $this->service->inboundMaxViews(), // burn-after-read for agents too
            'ttl_hours'            => $this->service->maxTtlHours(),
            'ip'                   => $request->ip(),
        ]);

        return $this->json(['status' => 'ok']);
    }

    /* ----------------------------------------------------------------- *
     | Helpers
     * ----------------------------------------------------------------- */

    private function sanitizeId($id): string
    {
        return preg_replace('/[^A-Za-z0-9\-_]/', '', (string) $id);
    }

    private function json(array $data, int $status = 200)
    {
        return response()->json($data, $status)
            ->header('Cache-Control', 'no-store');
    }
}
