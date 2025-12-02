<?php

namespace Gametech\FacebookOA\Http\Controllers;

use Gametech\FacebookOA\Models\FacebookAccount;
use Gametech\FacebookOA\Models\FacebookWebhookLog;
use Gametech\FacebookOA\Services\FacebookWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FacebookWebhookController extends Controller
{
    /**
     * Handle incoming webhook from Facebook Messenger.
     *
     * ตัวอย่าง URL:
     *   https://api.xxx.com/api/facebook-oa/webhook
     *
     * Facebook จะใช้:
     *   - GET  เพื่อ verify webhook (hub.mode / hub.verify_token / hub.challenge)
     *   - POST เพื่อส่ง events จริง (messages, postbacks, ฯลฯ)
     */
    public function handle(Request $request, FacebookWebhookService $service): JsonResponse
    {
        $method    = $request->getMethod();
        $ip        = $request->ip();
        $userAgent = $request->userAgent();
        $headers   = $request->headers->all();
        $requestId = (string) Str::uuid();

        if ($method === 'GET') {
            return $this->handleVerification($request, $requestId, $ip, $userAgent, $headers);
        }

        if ($method !== 'POST') {
            return response()->json([
                'message' => 'Method not allowed',
            ], 405);
        }

        return $this->handleEvent($request, $service, $requestId, $ip, $userAgent, $headers);
    }

    /**
     * จัดการ GET verification จาก Facebook:
     * - hub.mode=subscribe
     * - hub.verify_token=<webhook_verify_token>
     * - hub.challenge=<random>
     */
    protected function handleVerification(
        Request $request,
        string $requestId,
        ?string $ip,
        ?string $userAgent,
        array $headers
    ): JsonResponse {
        // Laravel จะ map ตัวแปร hub.mode => hub_mode (dot เป็น underscore)
        $mode         = $request->query('hub_mode') ?? $request->query('hub.mode');
        $verifyToken  = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge    = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        /** @var FacebookAccount|null $account */
        $account = null;

        if ($verifyToken) {
            $account = FacebookAccount::query()
                ->where('webhook_verify_token', $verifyToken)
                ->where('status', 'active')
                ->first();
        }

        if ($mode !== 'subscribe' || ! $account) {
            Log::channel('facebook_oa')->warning('[FacebookWebhook] verification failed', [
                'request_id'   => $requestId,
                'mode'         => $mode,
                'verify_token' => $verifyToken,
                'ip'           => $ip,
                'user_agent'   => $userAgent,
                'headers'      => $headers,
            ]);

            return response()->json([
                'message' => 'Verification failed',
            ], 403);
        }

        Log::channel('facebook_oa')->info('[FacebookWebhook] verification success', [
            'account_id'   => $account->id,
            'request_id'   => $requestId,
            'ip'           => $ip,
            'user_agent'   => $userAgent,
            'headers'      => $headers,
        ]);

        // Facebook คาดหวังให้ response เป็น challenge ตรง ๆ
        // แต่เราตอบเป็น JSON กลับไปก็ได้ (หลาย libs รองรับ)
        return response()->json($challenge, 200);
    }

    /**
     * จัดการ POST events จริงจาก Facebook
     */
    protected function handleEvent(
        Request $request,
        FacebookWebhookService $service,
        string $requestId,
        ?string $ip,
        ?string $userAgent,
        array $headers
    ): JsonResponse {
        // 1) raw body / json
        $rawBody = $request->getContent();
        $json    = json_decode($rawBody, true) ?: [];

        // 2) เดา page_id / event_type / event_id
        $firstEntry = $json['entry'][0] ?? null;
        $pageId     = $firstEntry['id'] ?? null;

        $firstMessaging = $firstEntry['messaging'][0] ?? null;

        if (isset($firstMessaging['message'])) {
            $eventType = 'message';
            $eventId   = $firstMessaging['message']['mid'] ?? null;
        } elseif (isset($firstMessaging['postback'])) {
            $eventType = 'postback';
            $eventId   = $firstMessaging['postback']['mid'] ?? null;
        } else {
            $eventType = $json['object'] ?? null; // 'page' ส่วนใหญ่
            $eventId   = null;
        }

        // 3) หา FacebookAccount จาก page_id
        /** @var FacebookAccount|null $account */
        $account = null;

        if ($pageId) {
            $account = FacebookAccount::query()
                ->where('page_id', $pageId)
                ->where('status', 'active')
                ->first();
        }

        // 4) สร้าง log record
        /** @var FacebookWebhookLog $log */
        $log = FacebookWebhookLog::create([
            'facebook_account_id' => $account?->id,
            'event_type'          => $eventType,
            'event_id'            => $eventId,
            'request_id'          => $requestId,
            'ip'                  => $ip,
            'user_agent'          => $userAgent,
            'headers'             => $headers,
            'body'                => $rawBody,
            'http_status'         => null,
            'is_processed'        => false,
            'processed_at'        => null,
            'error_message'       => null,
        ]);

        // 5) ถ้าไม่เจอ account ให้ตอบ 404
        if (! $account) {
            $message = 'Facebook Account not found for given page_id.';

            $log->update([
                'http_status'   => 404,
                'is_processed'  => false,
                'error_message' => $message,
            ]);

            Log::channel('facebook_oa')->warning('[FacebookWebhook] invalid page_id', [
                'page_id'    => $pageId,
                'request_id' => $requestId,
            ]);

            return response()->json([
                'message' => $message,
            ], 404);
        }

        // 6) ประมวลผล payload จริง
        try {
            $service->handle($account, $json, $log);

            $log->update([
                'http_status'  => 200,
                'is_processed' => true,
                'processed_at' => now(),
            ]);

            // Facebook ก็ต้องการ 200 OK เช่นกัน
            return response()->json([
                'message' => 'OK',
            ], 200);
        } catch (\Throwable $e) {
            $log->update([
                'http_status'   => 500,
                'is_processed'  => false,
                'error_message' => $e->getMessage(),
            ]);

            Log::channel('facebook_oa')->error('[FacebookWebhook] error while processing payload', [
                'account_id' => $account->id,
                'request_id' => $requestId,
                'error'      => $e->getMessage(),
            ]);

            // ป้องกัน retry loop: ส่วนใหญ่เราจะตอบ 200 กลับไปเหมือน LINE
            return response()->json([
                'message' => 'ERROR',
            ], 200);
        }
    }
}
