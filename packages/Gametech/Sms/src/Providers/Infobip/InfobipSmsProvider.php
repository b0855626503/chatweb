<?php

namespace Gametech\Sms\Providers\Infobip;

use Gametech\Sms\Contracts\SmsProviderInterface;
use Gametech\Sms\Models\SmsRecipient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Infobip SMS Provider (HTTP client, no SDK)
 *
 * หมายเหตุ:
 * - Flow หลักของ package ใช้ OutboundSmsService ใน SendSmsJob
 * - Provider นี้มีไว้สำหรับการใช้งานผ่าน SmsProviderInterface (ถ้าในอนาคต refactor)
 */
class InfobipSmsProvider implements SmsProviderInterface
{
    public function send(SmsRecipient $recipient, string $message, ?string $sender = null): array
    {
        $provider = 'infobip';

        $baseUrl = rtrim((string) config("sms.providers.$provider.credentials.base_url"), '/');
        $apiKey  = (string) config("sms.providers.$provider.credentials.api_key");
        $from    = (string) ($sender ?: config("sms.providers.$provider.credentials.from"));

        if ($baseUrl === '' || $apiKey === '' || $from === '') {
            return [
                'success' => false,
                'error_code' => 'MISSING_CREDENTIALS',
                'error_message' => 'Infobip credentials are missing (INFOBIP_BASE_URL / INFOBIP_API_KEY / INFOBIP_SMS_FROM).',
            ];
        }

        $endpoint = $baseUrl . '/sms/2/text/advanced';
        $timeout  = (int) env('INFOBIP_TIMEOUT', (int) env('SMS_HTTP_TIMEOUT', 10));

        $payload = [
            'messages' => [[
                'from' => $from,
                'destinations' => [
                    ['to' => (string) ($recipient->phone_e164 ?: $recipient->phone_raw)],
                ],
                'text' => $message,
            ]],
        ];

        $dlrUrl = (string) config("sms.providers.$provider.webhooks.dlr.url", '');
        if ($dlrUrl !== '') {
            $payload['messages'][0]['notifyUrl'] = $dlrUrl;
        }

        try {
            $res = Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => 'App ' . $apiKey,
                    'Accept' => 'application/json',
                ])
                ->asJson()
                ->post($endpoint, $payload);

            if (! $res->successful()) {
                $body = $res->json() ?? ['raw' => $res->body()];

                return [
                    'success' => false,
                    'error_code' => (string) (data_get($body, 'requestError.serviceException.messageId') ?: $res->status()),
                    'error_message' => (string) (data_get($body, 'requestError.serviceException.text') ?: 'Infobip HTTP error'),
                    'raw' => $body,
                ];
            }

            $data = (array) $res->json();
            $msg  = $data['messages'][0] ?? null;

            $messageId = (string) (is_array($msg) ? ($msg['messageId'] ?? '') : '');
            if ($messageId === '') {
                return [
                    'success' => false,
                    'error_code' => 'INVALID_RESPONSE',
                    'error_message' => 'Infobip response missing messageId',
                    'raw' => $data,
                ];
            }

            return [
                'success' => true,
                'provider_message_id' => $messageId,
                'raw' => $data,
            ];

        } catch (\Throwable $e) {
            Log::channel('sms')->error('[InfobipSmsProvider] exception', [
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_code' => 'EXCEPTION',
                'error_message' => $e->getMessage(),
            ];
        }
    }
}
