<?php

namespace Modules\Secrets\Services;

/**
 * Minimal SMSeagle HTTP API v2 client.
 *
 * FreeScout 1.8 ships on an older Laravel, so we avoid the Http facade and use
 * cURL directly to keep the module portable. Configuration comes from the admin
 * settings (\Option), falling back to env() for convenience.
 *
 * Docs: https://www.smseagle.eu/api/
 */
class SmsEagleClient
{
    private string $baseUrl;
    private string $token;
    private ?string $modem;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) (\Option::get('secrets.sms_base_url') ?: env('SMSEAGLE_BASE_URL', '')), '/');
        $this->token   = (string) (\Option::get('secrets.sms_token') ?: env('SMSEAGLE_TOKEN', ''));
        $this->modem   = \Option::get('secrets.sms_modem') ?: env('SMSEAGLE_MODEM');
        $this->timeout = (int) (\Option::get('secrets.sms_timeout') ?: env('SMSEAGLE_TIMEOUT', 10));
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->token !== '';
    }

    /**
     * Send an SMS. Returns the gateway message id (may be empty) on success,
     * throws \RuntimeException on any failure. The text is never logged.
     */
    public function send(string $to, string $text): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('SMSeagle is not configured.');
        }

        $payload = ['to' => [$to], 'text' => $text];
        if (!empty($this->modem)) {
            $payload['modem_no'] = (int) $this->modem;
        }

        $ch = curl_init($this->baseUrl . '/api/v2/messages/sms');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'access-token: ' . $this->token,
            ],
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status === 0) {
            \Log::warning('Secrets/SMSeagle transport error', ['to' => $to, 'error' => $err]);
            throw new \RuntimeException('SMSeagle transport error: ' . $err);
        }

        if ($status < 200 || $status >= 300) {
            \Log::warning('Secrets/SMSeagle send failed', ['to' => $to, 'status' => $status]);
            throw new \RuntimeException('SMSeagle responded with HTTP ' . $status . '.');
        }

        $json = json_decode((string) $body, true);
        $first = is_array($json) && array_keys($json) === range(0, count($json) - 1)
            ? ($json[0] ?? null)
            : $json;
        $smsStatus = is_array($first) ? ($first['status'] ?? null) : null;

        if ($smsStatus !== null && !in_array($smsStatus, ['queued', 'sent', 'ok'], true)) {
            \Log::warning('Secrets/SMSeagle rejected message', ['to' => $to, 'status' => $smsStatus]);
            throw new \RuntimeException('SMSeagle rejected the message (status=' . $smsStatus . ').');
        }

        return is_array($first) ? (string) ($first['id'] ?? $first['uuid'] ?? '') : '';
    }
}
