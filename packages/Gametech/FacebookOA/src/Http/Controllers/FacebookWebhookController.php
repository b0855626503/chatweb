<?php

namespace Gametech\FacebookOA\Http\Controllers;

use Gametech\FacebookOA\Models\FacebookAccount;
use Gametech\FacebookOA\Models\FacebookWebhookLog;
use Gametech\FacebookOA\Services\FacebookWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FacebookWebhookController extends Controller
{
    /**
     * Handle incoming webhook from Facebook Messenger.
     *
     * ตัวอย่าง URL:
     *   https://api168.168csn.com/api/facebook-oa/webhook/{token}
     *
     * Facebook จะใช้:
     *   - GET  เพื่อ verify webhook (hub.mode / hub.verify_token / hub.challenge)
     *   - POST เพื่อส่ง events จริง (messages, postbacks, ฯลฯ)
     */
    public function handle(Request $request, string $token, FacebookWebhookService $service)
    {
        $method = $request->getMethod();
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $headers = $request->headers->all();
        $requestId = (string) Str::uuid();

        try {
            Log::channel('facebook_oa')->info('[FacebookWebhook] incoming request', [
                'request_id' => $requestId,
                'method' => $method,
                'token_in_url' => $token,
                'query' => $request->query(),
                'ip' => $ip,
                'user_agent' => $userAgent,
            ]);

            if ($method === 'GET') {
                return $this->handleVerification($request, $token, $requestId, $ip, $userAgent, $headers);
            }

            if ($method !== 'POST') {
                return response()->json([
                    'message' => 'Method not allowed',
                ], 405);
            }

            return $this->handleEvent($request, $service, $token, $requestId, $ip, $userAgent, $headers);
        } catch (\Throwable $e) {
            Log::channel('facebook_oa')->error('[FacebookWebhook] unhandled exception', [
                'request_id' => $requestId,
                'token_in_url' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new Response('Internal Server Error', 500);
        }
    }

    /**
     * จัดการ GET verification จาก Facebook:
     * - hub.mode=subscribe
     * - hub.verify_token=<webhook_verify_token>
     * - hub.challenge=<random>
     */
    protected function handleVerification(
        Request $request,
        string $token,
        string $requestId,
        ?string $ip,
        ?string $userAgent,
        array $headers
    ) {
        // Laravel จะ map ตัวแปร hub.mode => hub_mode (dot เป็น underscore)
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $verifyToken = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        // หา account จาก token ใน URL (webhook_token)
        /** @var FacebookAccount|null $account */
        $account = FacebookAccount::query()
            ->where('webhook_token', $token)
            ->where('status', 'active')
            ->first();

        if (! $account || $mode !== 'subscribe') {
            Log::channel('facebook_oa')->warning('[FacebookWebhook] verification failed (account or mode)', [
                'request_id' => $requestId,
                'token_in_url' => $token,
                'mode' => $mode,
                'verify_token' => $verifyToken,
                'ip' => $ip,
                'user_agent' => $userAgent,
            ]);

            return response()->json([
                'message' => 'Verification failed',
            ], 403);
        }

        // เทียบ verify token จาก query string กับใน DB
        if ($verifyToken !== $account->webhook_verify_token) {
            Log::channel('facebook_oa')->warning('[FacebookWebhook] verification failed (invalid verify token)', [
                'request_id' => $requestId,
                'account_id' => $account->id,
                'token_in_url' => $token,
                'verify_token' => $verifyToken,
                'expected_token' => $account->webhook_verify_token,
                'ip' => $ip,
                'user_agent' => $userAgent,
            ]);

            return response()->json([
                'message' => 'Verification failed',
            ], 403);
        }

        Log::channel('facebook_oa')->info('[FacebookWebhook] verification success', [
            'request_id' => $requestId,
            'account_id' => $account->id,
            'page_id' => $account->page_id,
            'token_in_url' => $token,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);

        // สำคัญ: Facebook ต้องการให้ตอบ hub.challenge กลับเป็น plain text
        return new Response($challenge ?? '', 200, [
            'Content-Type' => 'text/plain',
        ]);
    }

    /**
     * จัดการ POST events จริงจาก Facebook
     */
    protected function handleEvent(
        Request $request,
        FacebookWebhookService $service,
        string $token,
        string $requestId,
        ?string $ip,
        ?string $userAgent,
        array $headers
    ) {
        // 1) raw body / json
        $rawBody = $request->getContent();
        $json = json_decode($rawBody, true) ?: [];

        // 2) เดา page_id / event_type / event_id
        $firstEntry = $json['entry'][0] ?? null;
        $pageId = $firstEntry['id'] ?? null;

        $firstMessaging = $firstEntry['messaging'][0] ?? null;

        if (isset($firstMessaging['message'])) {
            $eventType = 'message';
            $eventId = $firstMessaging['message']['mid'] ?? null;
        } elseif (isset($firstMessaging['postback'])) {
            $eventType = 'postback';
            $eventId = $firstMessaging['postback']['mid'] ?? null;
        } else {
            $eventType = $json['object'] ?? null; // 'page' ส่วนใหญ่
            $eventId = null;
        }

        // 3) หา FacebookAccount จาก page_id (หลัก)
        /** @var FacebookAccount|null $account */
        $account = null;

        if ($pageId) {
            $account = FacebookAccount::query()
                ->where('page_id', $pageId)
                ->where('status', 'active')
                ->first();
        }

        // ถ้าไม่เจอจาก page_id ลอง fallback หาโดย webhook_token จาก URL
        if (! $account) {
            $account = FacebookAccount::query()
                ->where('webhook_token', $token)
                ->where('status', 'active')
                ->first();
        }

        // 4) สร้าง log record
        /** @var FacebookWebhookLog $log */
        $log = FacebookWebhookLog::create([
            'facebook_account_id' => $account?->id,
            'event_type' => $eventType,
            'event_id' => $eventId,
            'request_id' => $requestId,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'headers' => $headers,
            'body' => $rawBody,
            'http_status' => null,
            'is_processed' => false,
            'processed_at' => null,
            'error_message' => null,
        ]);

        // 5) ถ้าไม่เจอ account ให้ตอบ 404
        if (! $account) {
            $message = 'Facebook Account not found for given page_id / token.';

            $log->update([
                'http_status' => 404,
                'is_processed' => false,
                'error_message' => $message,
            ]);

            Log::channel('facebook_oa')->warning('[FacebookWebhook] invalid account for event', [
                'request_id' => $requestId,
                'page_id' => $pageId,
                'token_in_url' => $token,
            ]);

            return response()->json([
                'message' => $message,
            ], 404);
        }

        // 6) ประมวลผล payload จริง
        try {
            $service->handle($account, $json, $log);

            $log->update([
                'http_status' => 200,
                'is_processed' => true,
                'processed_at' => now(),
            ]);

            // Facebook ต้องการ 200 OK
            return response()->json([
                'message' => 'OK',
            ], 200);
        } catch (\Throwable $e) {
            $log->update([
                'http_status' => 500,
                'is_processed' => false,
                'error_message' => $e->getMessage(),
            ]);

            Log::channel('facebook_oa')->error('[FacebookWebhook] error while processing payload', [
                'request_id' => $requestId,
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            // ป้องกัน retry loop: เลือกตอบ 200 กลับไป (เหมือนที่โบ๊ท ทำใน LINE OA)
            return response()->json([
                'message' => 'ERROR',
            ], 200);
        }
    }
}
