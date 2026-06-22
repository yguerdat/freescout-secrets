<?php

namespace Modules\Secrets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mailbox;
use Illuminate\Http\Request;
use Modules\Secrets\Entities\Secret;
use Modules\Secrets\Services\Crypto;
use Modules\Secrets\Services\SecretService;
use Modules\Secrets\Services\SmsEagleClient;

/**
 * Authenticated back-office controller: settings, the agent "create outbound
 * secret" page, SMS passphrase delivery and the in-ticket inbound reveal.
 */
class SecretsController extends Controller
{
    private SecretService $service;

    public function __construct()
    {
        $this->service = new SecretService();
    }

    /* ----------------------------------------------------------------- *
     | Settings (admin)
     * ----------------------------------------------------------------- */

    public function settings()
    {
        // Make sure the RSA key pair exists so the public key fingerprint can be
        // displayed and the intake form works immediately.
        (new Crypto())->ensureKeypair();

        return view('secrets::settings', [
            'mailboxes'        => Mailbox::all(),
            'brandName'        => \Option::get('company_name', config('app.name')),
            'tagline'          => \Option::get('secrets.tagline', ''),
            'accentColor'      => \Option::get('secrets.accent_color') ?: '#2563eb',
            'logoUrl'          => \Option::get('secrets.logo_url', ''),
            'publicBaseUrl'    => $this->service->publicBaseUrl(),
            'defaultTtlHours'  => $this->service->defaultTtlHours(),
            'defaultMaxViews'  => $this->service->defaultMaxViews(),
            'inboundEnabled'   => $this->service->inboundEnabled(),
            'inboundMailboxId' => (int) \Option::get('secrets.inbound_mailbox_id'),
            'smsBaseUrl'       => \Option::get('secrets.sms_base_url'),
            'smsModem'         => \Option::get('secrets.sms_modem'),
            'smsConfigured'    => (new SmsEagleClient())->isConfigured(),
        ]);
    }

    public function settingsSave(Request $request)
    {
        $url = rtrim(trim((string) $request->input('public_base_url')), '/');
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
            \Option::set('secrets.public_base_url', $url);
        }

        // Branding.
        \Option::set('secrets.tagline', trim((string) $request->input('tagline')));
        $accent = trim((string) $request->input('accent_color'));
        if ($accent === '' || preg_match('/^#[0-9a-fA-F]{3,8}$/', $accent)) {
            \Option::set('secrets.accent_color', $accent);
        }
        $logo = trim((string) $request->input('logo_url'));
        if ($logo === '' || filter_var($logo, FILTER_VALIDATE_URL)) {
            \Option::set('secrets.logo_url', $logo);
        }

        \Option::set('secrets.default_ttl_hours', max(1, (int) $request->input('default_ttl_hours', 168)));
        \Option::set('secrets.default_max_views', max(1, (int) $request->input('default_max_views', 1)));
        \Option::set('secrets.inbound_enabled', $request->has('inbound_enabled'));
        \Option::set('secrets.inbound_mailbox_id', (int) $request->input('inbound_mailbox_id', 0));

        \Option::set('secrets.sms_base_url', rtrim(trim((string) $request->input('sms_base_url')), '/'));
        if ($request->filled('sms_token')) {
            \Option::set('secrets.sms_token', trim((string) $request->input('sms_token')));
        }
        \Option::set('secrets.sms_modem', trim((string) $request->input('sms_modem')));

        \Session::flash('flash_success_floating', __('Settings saved.'));

        return redirect()->route('secrets.settings');
    }

    /* ----------------------------------------------------------------- *
     | Agent: create an outbound secret
     * ----------------------------------------------------------------- */

    public function createPage()
    {
        return view('secrets::create', [
            'publicBaseUrl'   => $this->service->publicBaseUrl(),
            'defaultTtlHours' => $this->service->defaultTtlHours(),
            'defaultMaxViews' => $this->service->defaultMaxViews(),
            'maxViewsCap'     => $this->service->maxViewsCap(),
            'maxTtlHours'     => $this->service->maxTtlHours(),
            'iterations'      => (int) config('secrets.pbkdf2_iterations', 310000),
            'smsConfigured'   => (new SmsEagleClient())->isConfigured(),
        ]);
    }

    public function storeOutbound(Request $request)
    {
        $maxBytes = (int) config('secrets.max_secret_bytes', 262144);

        $data = $request->validate([
            'ciphertext'           => 'required|string|max:' . ($maxBytes * 2),
            'salt'                 => 'required|string|max:128',
            'iv'                   => 'required|string|max:64',
            'passphrase_protected' => 'nullable|boolean',
            'ttl_hours'            => 'nullable|integer',
            'max_views'            => 'nullable|integer',
        ]);

        $secret = $this->service->store([
            'direction'            => Secret::DIRECTION_OUTBOUND,
            'ciphertext'           => $data['ciphertext'],
            'salt'                 => $data['salt'],
            'iv'                   => $data['iv'],
            'passphrase_protected' => !empty($data['passphrase_protected']),
            'max_views'            => $data['max_views'] ?? null,
            'ttl_hours'            => $data['ttl_hours'] ?? null,
            'created_by'           => auth()->id(),
            'ip'                   => $request->ip(),
        ]);

        return response()->json([
            'id'         => $secret->id,
            'url'        => $this->service->publicBaseUrl() . '/secrets/s/' . $secret->id,
            'expires_at' => $secret->expires_at->toIso8601String(),
        ]);
    }

    /* ----------------------------------------------------------------- *
     | Agent: send the passphrase by SMS
     * ----------------------------------------------------------------- */

    public function sendSms(Request $request)
    {
        $data = $request->validate([
            'to'         => 'required|string|max:32',
            'passphrase' => 'required|string|max:256',
        ]);

        // Normalise to E.164-ish: keep leading + and digits only.
        $to = preg_replace('/(?!^\+)[^\d]/', '', trim($data['to']));

        try {
            $text = __('Your passphrase to open the secure link: :code', ['code' => $data['passphrase']]);
            (new SmsEagleClient())->send($to, $text);
        } catch (\Throwable $e) {
            \Log::warning('Secrets: SMS send failed: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'ok']);
    }

    /* ----------------------------------------------------------------- *
     | Agent: reveal an inbound secret from the ticket
     * ----------------------------------------------------------------- */

    public function revealInbound(Request $request, $token)
    {
        $id = preg_replace('/[^A-Za-z0-9\-_]/', '', (string) $token);
        $secret = $this->service->peek($id);

        if (!$secret || $secret->direction !== Secret::DIRECTION_INBOUND) {
            return response()->json(['status' => 'gone']);
        }

        // The agent must be allowed to see the linked conversation.
        if ($secret->conversation_id) {
            $conversation = \App\Conversation::find($secret->conversation_id);
            if ($conversation && !auth()->user()->can('view', $conversation)) {
                return response()->json(['status' => 'forbidden'], 403);
            }
        }

        $result = $this->service->consume($id);
        if ($result['status'] !== 'ok') {
            return response()->json(['status' => 'gone']);
        }

        $p = $result['payload'];

        try {
            // Unwrap the content key (RSA private key sealed with APP_KEY).
            $key = (new Crypto())->unwrapKey($p['wrapped_key']);
        } catch (\Throwable $e) {
            \Log::error('Secrets: inbound unwrap failed: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }

        return response()->json([
            'status'               => 'ok',
            'ciphertext'           => $p['ciphertext'],
            'salt'                 => $p['salt'],
            'iv'                   => $p['iv'],
            'key'                  => rtrim(strtr(base64_encode($key), '+/', '-_'), '='),
            'passphrase_protected' => (bool) $p['passphrase_protected'],
        ])->header('Cache-Control', 'no-store');
    }
}
