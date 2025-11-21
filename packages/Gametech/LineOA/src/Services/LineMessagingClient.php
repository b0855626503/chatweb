<?php

namespace Gametech\LineOa\Services;

use Gametech\LineOa\Models\LineAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LineMessagingClient
{
    /**
     * base URL ของ LINE Messaging API
     *
     * @var string
     */
    protected string $baseUrl = 'https://api.line.me';

    /**
     * เตรียม Http client สำหรับ OA หนึ่งตัว
     *
     * - ใส่ access token ของ OA นั้น
     * - ตั้งค่า timeout แบบอ่านจาก config line_oa (มี default เผื่อ)
     */
    protected function http(LineAccount $account)
    {
        $timeout = (int) (config('line_oa.http_timeout', 5));

        return Http::withToken($account->access_token)
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout($timeout);
    }

    /**
     * ส่งข้อความ text แบบ push (ใช้เวลาแอดมินตอบลูกค้า / บอทแจ้งเอง)
     *
     * @param LineAccount $account     OA ที่จะใช้ยิงข้อความ
     * @param string      $toUserId    line_user_id ของลูกค้า (จาก LineContact)
     * @param string      $text        ข้อความ
     * @param array       $extraPayload สำหรับใส่ field เพิ่มใน message (เช่น quickReply ฯลฯ)
     *
     * @return array{success: bool, status: int|null, body: mixed, error: string|null}
     */
    public function pushText(LineAccount $account, string $toUserId, string $text, array $extraPayload = []): array
    {
        $message = array_merge([
            'type' => 'text',
            'text' => $text,
        ], $extraPayload);

        $payload = [
            'to'       => $toUserId,
            'messages' => [$message],
        ];

        return $this->sendRequest($account, '/v2/bot/message/push', $payload, 'pushText');
    }

    /**
     * ส่งหลายข้อความ (ข้อความ text หลายอัน หรือ mix type ก็ได้) แบบ push
     *
     * @param LineAccount $account
     * @param string      $toUserId
     * @param array       $messages  array ของ message objects ตาม spec LINE
     *
     * @return array
     */
    public function pushMessages(LineAccount $account, string $toUserId, array $messages): array
    {
        $payload = [
            'to'       => $toUserId,
            'messages' => $messages,
        ];

        return $this->sendRequest($account, '/v2/bot/message/push', $payload, 'pushMessages');
    }

    /**
     * ส่งข้อความตอบกลับแบบ reply (ใช้ได้เฉพาะภายใน window เวลาที่ LINE เปิดให้)
     *
     * ใช้ตอนเราอยาก reply ทันทีจาก webhook (ไม่ใช่กรณี admin มาตอบทีหลัง)
     */
    public function replyText(LineAccount $account, string $replyToken, string $text, array $extraPayload = []): array
    {
        $message = array_merge([
            'type' => 'text',
            'text' => $text,
        ], $extraPayload);

        $payload = [
            'replyToken' => $replyToken,
            'messages'   => [$message],
        ];

        return $this->sendRequest($account, '/v2/bot/message/reply', $payload, 'replyText');
    }

    /**
     * low-level: ส่ง request จริง แล้วรวมผลลัพธ์ออกมาเป็น array เดียว
     *
     * @return array{success: bool, status: int|null, body: mixed, error: string|null}
     */
    protected function sendRequest(LineAccount $account, string $uri, array $payload, string $context): array
    {
        try {
            /** @var Response $response */
            $response = $this->http($account)->post($uri, $payload);

            $success = $response->successful();
            $status  = $response->status();
            $body    = $response->json();

            if (! $success) {
                $errorBody = $response->body();

                Log::warning('[LineMessagingClient] request failed', [
                    'account_id' => $account->id,
                    'context'    => $context,
                    'uri'        => $uri,
                    'status'     => $status,
                    'payload'    => $payload,
                    'response'   => $errorBody,
                ]);

                return [
                    'success' => false,
                    'status'  => $status,
                    'body'    => $body ?? $errorBody,
                    'error'   => is_string($errorBody) ? $errorBody : json_encode($errorBody),
                ];
            }

            Log::info('[LineMessagingClient] request success', [
                'account_id' => $account->id,
                'context'    => $context,
                'uri'        => $uri,
                'status'     => $status,
            ]);

            return [
                'success' => true,
                'status'  => $status,
                'body'    => $body,
                'error'   => null,
            ];
        } catch (\Throwable $e) {
            Log::error('[LineMessagingClient] exception', [
                'account_id' => $account->id,
                'context'    => $context,
                'uri'        => $uri,
                'payload'    => $payload,
                'error'      => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status'  => null,
                'body'    => null,
                'error'   => $e->getMessage(),
            ];
        }
    }
}
