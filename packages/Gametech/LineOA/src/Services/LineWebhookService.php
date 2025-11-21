<?php

namespace Gametech\LineOA\Services;

use Gametech\LineOA\Models\LineAccount;
use Gametech\LineOA\Models\LineWebhookLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class LineWebhookService
{
    protected ChatService $chat;

    public function __construct(ChatService $chat)
    {
        $this->chat = $chat;
    }

    /**
     * จุดเริ่มต้นในการประมวลผล payload จาก LINE ทั้งก้อน
     *
     * @param  array  $payload  JSON decode จาก body
     */
    public function handle(LineAccount $account, array $payload, ?LineWebhookLog $log = null): void
    {
        $events = $payload['events'] ?? [];

        if (empty($events)) {
            Log::info('[LineWebhook] empty events', [
                'account_id' => $account->id,
                'log_id' => $log?->id,
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
        $messageId   = Arr::get($event, 'message.id');

        // log ตาม type เดิม ๆ ไว้ก่อน (ไม่ตัด pattern เดิมทิ้ง)
        if ($messageType === 'text') {
            Log::info('[LineWebhook] receive text message', [
                'account_id' => $account->id,
                'message_id' => $messageId,
            ]);
        } elseif ($messageType === 'sticker') {
            Log::info('[LineWebhook] receive sticker', [
                'account_id' => $account->id,
                'message_id' => $messageId,
            ]);
        } elseif ($messageType === 'image') {
            Log::info('[LineWebhook] receive image', [
                'account_id' => $account->id,
                'message_id' => $messageId,
            ]);
        } else {
            Log::info('[LineWebhook] receive non-text event', [
                'account_id'   => $account->id,
                'message_id'   => $messageId,
                'message_type' => $messageType,
            ]);
        }

        // ไม่ว่า type อะไร ให้เก็บลง DB ผ่าน ChatService.handleIncomingMessage เสมอ
        try {
            // 1) เก็บ message + contact + conversation ลง DB
            /** @var \Gametech\LineOA\Models\LineMessage $message */
            $message = $this->chat->handleIncomingMessage($account, $event, $log);
        } catch (\Throwable $e) {
            Log::error('[LineWebhook] handleMessageEvent exception', [
                'account_id'   => $account->id,
                'message_id'   => $messageId,
                'message_type' => $messageType,
                'error'        => $e->getMessage(),
            ]);

            // ถ้าต้องการให้ error เด้งต่อออกไปก็ throw ต่อได้
            throw $e;
        }

        if ($messageType === 'text') {
            $text = $message->text ?? '';

            // ------------------------------------------------------------------
            //  เพิ่ม: ให้ RegisterFlowService ลองจัดการ flow "สมัครสมาชิก"
            // ------------------------------------------------------------------
            try {
                $contact      = $message->contact ?? null;
                $conversation = $message->conversation ?? null;

                if ($contact && $conversation) {
                    /** @var \Gametech\LineOA\Services\RegisterFlowService $registerFlow */
                    $registerFlow = app(\Gametech\LineOA\Services\RegisterFlowService::class);

                    $flowResult = $registerFlow->handleTextMessage(
                        $contact,
                        $conversation,
                        $text
                    );

                    if ($flowResult && $flowResult->handled && $flowResult->replyText) {
                        $replyToken = Arr::get($event, 'replyToken');

                        if ($replyToken) {
                            /** @var \Gametech\LineOA\Services\LineMessagingClient $messaging */
                            $messaging = app(\Gametech\LineOA\Services\LineMessagingClient::class);

                            try {
                                $messaging->replyText($account, $replyToken, $flowResult->replyText);
                            } catch (\Throwable $e) {
                                Log::error('[LineWebhook] replyText failed (register flow)', [
                                    'account_id'      => $account->id,
                                    'line_message_id' => $message->line_message_id ?? null,
                                    'conversation_id' => $message->line_conversation_id ?? null,
                                    'contact_id'      => $message->line_contact_id ?? null,
                                    'error'           => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error('[LineWebhook] register flow error', [
                    'account_id' => $account->id,
                    'event'      => $event,
                    'error'      => $e->getMessage(),
                ]);
            }

            // ------------------------------------------------------------------
            //  log เดิม: เก็บรอยข้อความไว้ (ของเก่าใช้งานอยู่แล้ว)
            // ------------------------------------------------------------------
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

        // upsert contact + ดึง profile จาก LINE มาเก็บ
        $contact = $this->chat->updateContactProfile($account, $userId);

        Log::info('[LineWebhook] follow event', [
            'account_id'   => $account->id,
            'line_user_id' => $userId,
            'contact_id'   => $contact->id,
        ]);

        // TODO: จะส่งข้อความต้อนรับก็เรียก LineMessagingClient::pushText / replyText ต่อจากตรงนี้ได้
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
     * ตัวอย่าง helper เช็ค keyword สมัคร (ยังเก็บไว้ เผื่อใช้กรอง trigger เพิ่มเติม)
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
