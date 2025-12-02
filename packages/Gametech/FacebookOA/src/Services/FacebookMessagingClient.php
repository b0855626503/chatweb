<?php

namespace Gametech\FacebookOA\Services;

use Gametech\FacebookOA\Models\FacebookAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookMessagingClient
{
    /**
     * base URL ของ Facebook Graph API
     */
    protected string $baseUrl = 'https://graph.facebook.com/v19.0';

    /**
     * คืน Http client สำหรับยิงไป Facebook (JSON)
     */
    public function http(FacebookAccount $account): \Illuminate\Http\Client\PendingRequest
    {
        $timeout = (int) (config('facebook_oa.http_timeout', 5));

        return Http::withToken($account->page_access_token)
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout($timeout);
    }

    /**
     * ส่งข้อความ text หา user (PSID)
     *
     * @param  string  $recipientId  PSID ของผู้ใช้
     * @param  string  $text  ข้อความที่ต้องการส่ง
     * @param  array  $extraPayload  เช่น ['quick_replies' => [...]]
     * @param  string  $messagingType  RESPONSE / UPDATE / MESSAGE_TAG
     * @return array{success: bool, status: int|null, body: mixed, error: string|null}
     */
    public function sendText(
        FacebookAccount $account,
        string $recipientId,
        string $text,
        array $extraPayload = [],
        string $messagingType = 'RESPONSE'
    ): array {
        $message = array_merge([
            'text' => $text,
        ], $extraPayload);

        $payload = [
            'recipient' => ['id' => $recipientId],
            'message' => $message,
            'messaging_type' => $messagingType,
        ];

        return $this->sendRequest($account, '/me/messages', $payload, 'sendText');
    }

    /**
     * ส่งข้อความรูปภาพ (image attachment)
     *
     * @param  bool  $isReusable  ให้ Facebook ทำเป็น reusable attachment หรือไม่
     * @return array{success: bool, status: int|null, body: mixed, error: string|null}
     */
    public function sendImageMessage(
        FacebookAccount $account,
        string $recipientId,
        string $imageUrl,
        bool $isReusable = false,
        string $messagingType = 'RESPONSE'
    ): array {
        $message = [
            'attachment' => [
                'type' => 'image',
                'payload' => [
                    'url' => $imageUrl,
                    'is_reusable' => $isReusable,
                ],
            ],
        ];

        $payload = [
            'recipient' => ['id' => $recipientId],
            'message' => $message,
            'messaging_type' => $messagingType,
        ];

        return $this->sendRequest($account, '/me/messages', $payload, 'sendImageMessage');
    }

    /**
     * ส่ง payload แบบ raw ไป /me/messages
     *
     * ใช้เวลาต้องการ custom template / quick replies / buttons เอง
     *
     * @param  array  $payload  payload เต็มสำหรับ /me/messages
     * @return array{success: bool, status: int|null, body: mixed, error: string|null}
     */
    public function sendMessages(FacebookAccount $account, array $payload): array
    {
        return $this->sendRequest($account, '/me/messages', $payload, 'sendMessages');
    }

    /**
     * ดึง profile ของ user จาก Facebook Graph API
     *
     * @param  string  $userId  PSID ของผู้ใช้
     * @return array{success: bool, status: int|null, body: mixed, error: string|null}
     */
    public function getProfile(FacebookAccount $account, string $userId): array
    {
        $uri = '/'.$userId;

        $query = [
            'fields' => 'first_name,last_name,profile_pic,locale,timezone,gender',
        ];

        try {
            /** @var Response $response */
            $response = $this->http($account)->get($uri, $query);

            $success = $response->successful();
            $status = $response->status();
            $body = $response->json();

            if (! $success) {
                $errorBody = $response->body();

                Log::channel('facebook_oa')->warning('[FacebookMessagingClient] getProfile failed', [
                    'account_id' => $account->id,
                    'uri' => $uri,
                    'status' => $status,
                    'query' => $query,
                    'response' => $errorBody,
                ]);

                return [
                    'success' => false,
                    'status' => $status,
                    'body' => $body ?? $errorBody,
                    'error' => is_string($errorBody) ? $errorBody : json_encode($errorBody),
                ];
            }

            Log::channel('facebook_oa')->info('[FacebookMessagingClient] getProfile success', [
                'account_id' => $account->id,
                'uri' => $uri,
                'status' => $status,
            ]);

            return [
                'success' => true,
                'status' => $status,
                'body' => $body,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::channel('facebook_oa')->error('[FacebookMessagingClient] getProfile exception', [
                'account_id' => $account->id,
                'uri' => $uri,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => null,
                'body' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * core method สำหรับยิง POST ไปยัง Facebook Graph API (JSON)
     *
     *
     * @return array{success: bool, status: int|null, body: mixed, error: string|null}
     */
    protected function sendRequest(
        FacebookAccount $account,
        string $uri,
        array $payload,
        string $context
    ): array {
        try {
            /** @var Response $response */
            $response = $this->http($account)->post($uri, $payload);

            $success = $response->successful();
            $status = $response->status();
            $body = $response->json();

            if ($status >= 200 && $status < 300) {
                Log::channel('facebook_oa')->info('[FacebookMessagingClient] request success', [
                    'account_id' => $account->id,
                    'context' => $context,
                    'uri' => $uri,
                    'status' => $status,
                    'payload' => $payload,
                ]);

                return [
                    'success' => true,
                    'status' => $status,
                    'body' => $body,
                    'error' => null,
                ];
            }

            if (! $success) {
                $errorBody = $response->body();

                Log::channel('facebook_oa')->warning('[FacebookMessagingClient] request failed', [
                    'account_id' => $account->id,
                    'context' => $context,
                    'uri' => $uri,
                    'status' => $status,
                    'payload' => $payload,
                    'response' => $errorBody,
                ]);

                return [
                    'success' => false,
                    'status' => $status,
                    'body' => $body ?? $errorBody,
                    'error' => is_string($errorBody) ? $errorBody : json_encode($errorBody),
                ];
            }

            Log::channel('facebook_oa')->info('[FacebookMessagingClient] request success (non-error)', [
                'account_id' => $account->id,
                'context' => $context,
                'uri' => $uri,
                'status' => $status,
            ]);

            return [
                'success' => true,
                'status' => $status,
                'body' => $body,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::channel('facebook_oa')->error('[FacebookMessagingClient] exception', [
                'account_id' => $account->id,
                'context' => $context,
                'uri' => $uri,
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => null,
                'body' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
