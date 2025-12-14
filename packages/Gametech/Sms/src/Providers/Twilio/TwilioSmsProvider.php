<?php

namespace Gametech\Sms\Providers\Twilio;

use Gametech\Sms\Contracts\SmsProviderInterface;
use Gametech\Sms\Models\SmsRecipient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TwilioSmsProvider implements SmsProviderInterface
{
    protected string $accountSid;
    protected string $authToken;
    protected string $endpoint;

    public function __construct()
    {
        // ให้ตรงกับ config/sms.php (credentials.*)
        $this->accountSid = (string) config('sms.providers.twilio.credentials.account_sid');
        $this->authToken  = (string) config('sms.providers.twilio.credentials.auth_token');

        $this->endpoint = $this->accountSid !== ''
            ? "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json"
            : 'https://api.twilio.com/2010-04-01/Accounts//Messages.json';
    }

    public function send(SmsRecipient $recipient, string $message, ?string $sender = null): array
    {
        try {
            $from = (string) ($sender ?: config('sms.providers.twilio.credentials.from'));
            $to   = $this->normalizeE164((string) $recipient->phone_e164);

            if ($this->accountSid === '' || $this->authToken === '' || $from === '' || $to === '') {
                return [
                    'success' => false,
                    'error_message' => 'MISSING_CREDENTIALS_OR_TO',
                ];
            }

            $payload = [
                'From' => $from,
                'To'   => $to,
                'Body' => $message,
            ];

            $callbackUrl = (string) config('sms.providers.twilio.webhooks.dlr.url', '');
            if ($callbackUrl !== '') {
                $payload['StatusCallback'] = $callbackUrl;
            }

            $timeout = (int) env('TWILIO_TIMEOUT', (int) env('SMS_HTTP_TIMEOUT', 10));

            $response = Http::timeout($timeout)
                ->asForm()
                ->withBasicAuth($this->accountSid, $this->authToken)
                ->post($this->endpoint, $payload);

            if (! $response->successful()) {
                $body = $response->json() ?? ['raw' => $response->body()];

                return [
                    'success'       => false,
                    'error_code'    => (string) ($body['code'] ?? $response->status()),
                    'error_message' => (string) ($body['message'] ?? 'SEND_FAILED'),
                    'raw'           => $body,
                ];
            }

            $data = (array) $response->json();
            $sid  = (string) ($data['sid'] ?? '');

            if ($sid === '') {
                return [
                    'success' => false,
                    'error_message' => 'INVALID_PROVIDER_RESPONSE',
                    'raw' => $data,
                ];
            }

            return [
                'success' => true,
                'provider_message_id' => $sid,
            ];

        } catch (\Throwable $e) {
            Log::channel('sms')->error('[TwilioSmsProvider] exception', [
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    private function normalizeE164(string $phone): string
    {
        $p = preg_replace('/[^0-9+]/', '', $phone) ?? '';
        if ($p === '') {
            return '';
        }

        if (! Str::startsWith($p, '+')) {
            $p = '+' . $p;
        }

        return $p;
    }
}
