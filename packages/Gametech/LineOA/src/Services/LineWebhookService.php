<?php

namespace Gametech\LineOa\Services;

use Gametech\LineOa\Models\LineAccount;
use Gametech\LineOa\Models\LineWebhookLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class LineWebhookService
{
    /** @var \Gametech\LineOa\Services\ChatService */
    protected ChatService $chat;

    public function __construct(ChatService $chat)
    {
        $this->chat = $chat;
    }

    /**
     * จุดเริ่มต้นในการประมวลผล payload จาก LINE ทั้งก้อน
     *
     * @param  \Gametech\LineOa\Models\LineAccount   $account
     * @param  array                                 $payload JSON decode จาก body
     * @param  \Gametech\LineOa\Models\LineWebhookLog|null $log
     * @return void
     */
    public function handle(LineAccount $account, array $payload, ?LineWebhookLog $log = null): void
    {
        $events = $payload['events'] ?? [];

        if (empty($events)) {
            Log::info('[LineWebhook] empty events', [
                'account_id' => $account->id,
                'log_id'     => $log?->id,
            ]);

            return;
        }

        foreach ($events as $event) {
            $type = Arr::get($event, 'type');

            try {
                switch ($type) {
                    case 'message':
                        $this->handleMessageEvent($account, $event, $log);
                        break;

                    case 'follow':
                        $this->handleFollowEvent($account, $event, $log);
                        break;

                    case 'unfollow':
                        $this->handleUnfollowEvent($account, $event, $log);
                        break;

                    case 'postback':
                        $this->handlePostbackEvent($account, $event, $log);
                        break;

                    case 'join':
                    case 'leave':
                    case 'memberJoined':
                    case 'memberLeft':
                        $this->handleGenericEvent($account, $event, $log);
                        break;

                    default:
                        $this->handleUnknownEvent($account, $event, $log);
                        break;
                }
            } catch (\Throwable $e) {
                Log::error('[LineWebhook] error on event', [
                    'account_id' => $account->id,
                    'event'      => $event,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * message event -> ข้อความจากลูกค้า (หรือเราเอง)
     */
    protected function handleMessageEvent(LineAccount $account, array $event, ?LineWebhookLog $log = null): void
    {
        $messageType = Arr::get($event, 'message.type');

        // ตอนนี้โฟกัส text ก่อน ประเภทอื่นทีหลัง
        if ($messageType === 'text') {
            // 1) เก็บ chat ลง DB
            $message = $this->chat->handleIncomingMessage($account, $event, $log);

            $text = $message->text ?? '';

            // 2) TODO: trigger flow ตามข้อความ เช่น สมัคร/โปร/คู่มือ ฯลฯ
            //    ตัวอย่างตรวจคำว่า "สมัคร" แบบง่าย ๆ (เผื่อใช้ตอนทำ RegisterFlowService)
            //
            //    if ($this->isRegisterKeyword($text)) {
            //        $this->registerFlow->handle($account, $message);
            //    }

            Log::info('[LineWebhook] message stored', [
                'account_id'      => $account->id,
                'line_message_id' => $message->line_message_id,
                'conversation_id' => $message->line_conversation_id,
                'contact_id'      => $message->line_contact_id,
                'text'            => $text,
            ]);

            return;
        }

        // สำหรับ message type อื่น เช่น image, sticker, file, location ฯลฯ
        Log::info('[LineWebhook] message event (non-text)', [
            'account_id' => $account->id,
            'event'      => $event,
        ]);

        // ถ้าอยากเก็บ media ด้วยก็สามารถใช้ ChatService อีก method หนึ่งในอนาคต
    }

    /**
     * follow event -> ลูกค้า add OA / unblock
     */
    protected function handleFollowEvent(LineAccount $account, array $event, ?LineWebhookLog $log = null): void
    {
        $userId = Arr::get($event, 'source.userId');

        Log::info('[LineWebhook] follow event', [
            'account_id'   => $account->id,
            'line_user_id' => $userId,
        ]);

        // TODO:
        // - upsert LineContact (line_account_id + line_user_id)
        // - เคลียร์ blocked_at
        // - ดึง profile จาก LINE API (optional)
        // - ส่งข้อความต้อนรับ (ใช้ LineTemplate)
    }

    /**
     * unfollow event -> ลูกค้า block OA
     */
    protected function handleUnfollowEvent(LineAccount $account, array $event, ?LineWebhookLog $log = null): void
    {
        $userId = Arr::get($event, 'source.userId');

        Log::info('[LineWebhook] unfollow event', [
            'account_id'   => $account->id,
            'line_user_id' => $userId,
        ]);

        // TODO:
        // - update LineContact->blocked_at = now()
    }

    /**
     * postback event เช่น กดปุ่ม template / quick reply
     */
    protected function handlePostbackEvent(LineAccount $account, array $event, ?LineWebhookLog $log = null): void
    {
        $userId = Arr::get($event, 'source.userId');
        $data   = Arr::get($event, 'postback.data');
        $params = Arr::get($event, 'postback.params', []);

        Log::info('[LineWebhook] postback event', [
            'account_id'   => $account->id,
            'line_user_id' => $userId,
            'data'         => $data,
            'params'       => $params,
        ]);

        // TODO:
        // - parse $data เช่น "action=register_confirm&session_id=123"
        // - ส่งต่อไปยัง flow ที่เกี่ยวข้อง
    }

    /**
     * generic handler สำหรับ event ที่เรายังไม่ได้สนใจมาก เช่น join/leave group
     */
    protected function handleGenericEvent(LineAccount $account, array $event, ?LineWebhookLog $log = null): void
    {
        Log::info('[LineWebhook] generic event', [
            'account_id' => $account->id,
            'event'      => $event,
        ]);
    }

    /**
     * กรณี event type แปลก ๆ หรือไม่รู้จัก
     */
    protected function handleUnknownEvent(LineAccount $account, array $event, ?LineWebhookLog $log = null): void
    {
        Log::warning('[LineWebhook] unknown event type', [
            'account_id' => $account->id,
            'event'      => $event,
        ]);
    }

    /**
     * ตัวอย่าง helper เช็ค keyword สมัคร (ไว้ใช้ตอนต่อ RegisterFlowService)
     */
    protected function isRegisterKeyword(?string $text): bool
    {
        if (! $text) {
            return false;
        }

        $text = trim(mb_strtolower($text));

        // เพิ่มคำที่ลูกค้ามักใช้ขอสมัคร ได้เรื่อย ๆ
        $keywords = [
            'สมัคร',
            'สมัครสมาชิก',
            'reg',
            'register',
        ];

        foreach ($keywords as $kw) {
            if ($text === $kw) {
                return true;
            }
        }

        return false;
    }
}
