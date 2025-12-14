<?php

namespace Gametech\Sms\Providers\Infobip;

use Gametech\Sms\Contracts\SmsProviderInterface;
use Gametech\Sms\Models\SmsRecipient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InfobipSmsProvider implements SmsProviderInterface
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('sms.providers.infobip.credentials.base_url'), '/');
        $this->apiKey  = (string) config('sms.providers.infobip.credentials.api_key');
    }

    public function send(SmsRecipient $recipient, string $message, ?string $sender = null): array
    {
        try {
            if ($this->baseUrl === '' || $this->apiKey === '') {
                return [
                    'success' => false,
                    'error_message' => 'MISSING_INFOBIP_CREDENTIALS',
                ];
            }

            $from = $sender ?: (string) config('sms.providers.infobip.credentials.from');

            $payload = [
                'messages' => [[
                    'from' => $from,
                    'destinations' => [
                        ['to' => $recipient->phone_e164],
                    ],
                    'text' => $message,
                ]],
            ];

            $dlrUrl = (string) config('sms.providers.infobip.webhooks.dlr.url');
            if ($dlrUrl !== '') {
                $payload['messages'][0]['notifyUrl'] = $dlrUrl;
            }

            $response = Http::withHeaders([
                'Authorization' => 'App ' . $this->apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])
                ->post($this->baseUrl . '/sms/2/text/advanced', $payload);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error_code' => $response->status(),
                    'error_message' => $response->body(),
                ];
            }

            $data = $response->json();
            $msg  = $data['messages'][0] ?? null;

            if (! $msg || empty($msg['messageId'])) {
                return [
                    'success' => false,
                    'error_message' => 'INVALID_PROVIDER_RESPONSE',
                    'raw' => $data,
                ];
            }

            return [
                'success' => true,
                'provider_message_id' => $msg['messageId'],
                'raw' => $data,
            ];

        } catch (\Throwable $e) {
            Log::channel('sms')->error('[InfobipSmsProvider] exception', [
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_message' => $e->getMessage(),
            ];
        }
    }
}
