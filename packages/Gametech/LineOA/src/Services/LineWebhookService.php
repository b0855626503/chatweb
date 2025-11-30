<?php

namespace Gametech\LineOA\Services;

use Gametech\LineOA\Models\LineAccount;
use Gametech\LineOA\Models\LineMessage;
use Gametech\LineOA\Models\LineWebhookLog;
use Gametech\LineOA\Support\UrlHelper;
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
                    'event' => $event,
                    'error' => $e->getMessage(),
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
        $messageId = Arr::get($event, 'message.id');

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
                'account_id' => $account->id,
                'message_id' => $messageId,
                'message_type' => $messageType,
            ]);
        }

        // ไม่ว่า type อะไร ให้เก็บลง DB ผ่าน ChatService.handleIncomingMessage เสมอ
        try {
            /** @var LineMessage $message */
            $message = $this->chat->handleIncomingMessage($account, $event, $log);
        } catch (\Throwable $e) {
            Log::error('[LineWebhook] handleMessageEvent exception', [
                'account_id' => $account->id,
                'message_id' => $messageId,
                'message_type' => $messageType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        // ------------------------------------------------------------------
        //  เช็คว่าเป็นข้อความ inbound แรกของห้องหรือไม่ → ส่งข้อความต้อนรับ
        // ------------------------------------------------------------------
        try {
            $contact = $message->contact ?? null;
            $conversation = $message->conversation ?? null;

            if ($contact && $conversation && $message->direction === 'inbound') {
                $isFirstInbound = ! LineMessage::query()
                    ->where('line_conversation_id', $conversation->id)
                    ->where('direction', 'inbound')
                    ->where('id', '<', $message->id)
                    ->exists();

                if ($isFirstInbound) {
                    $this->handleWelcomeForFirstMessage($account, $event, $message);
                }
            }
        } catch (\Throwable $e) {
            Log::error('[LineWebhook] welcome flow error', [
                'account_id' => $account->id,
                'line_message_id' => $message->line_message_id ?? null,
                'conversation_id' => $message->line_conversation_id ?? null,
                'contact_id' => $message->line_contact_id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        // เฉพาะ text ถึงโยนเข้า flow เพิ่ม
        if ($messageType === 'text') {
            $text = $message->text ?? '';

            // ------------------------------------------------------------------
            //  ให้ RegisterFlowService ลองจัดการ flow "สมัครสมาชิก"
            // ------------------------------------------------------------------
            try {
                $contact = $message->contact ?? null;
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
                        // เก็บข้อความที่ BOT ตอบกลับในการสมัครสมาชิกลง line_messages
                        try {
                            LineMessage::create([
                                'line_conversation_id' => $message->line_conversation_id,
                                'line_account_id' => $message->line_account_id,
                                'line_contact_id' => $message->line_contact_id,
                                'direction' => 'outbound',
                                'source' => 'bot',
                                'type' => 'text',
                                'line_message_id' => null,
                                'text' => $flowResult->replyText,
                                'payload' => null,
                                'meta' => null,
                                'sender_employee_id' => null,
                                'sender_bot_key' => 'register_flow',
                                'sent_at' => now(),
                            ]);
                        } catch (\Throwable $e) {
                            Log::error('[LineWebhook] store bot message failed (register flow)', [
                                'account_id' => $account->id,
                                'line_message_id' => $message->line_message_id ?? null,
                                'conversation_id' => $message->line_conversation_id ?? null,
                                'contact_id' => $message->line_contact_id ?? null,
                                'error' => $e->getMessage(),
                            ]);
                        }

                        $replyToken = Arr::get($event, 'replyToken');

                        if ($replyToken) {
                            /** @var LineMessagingClient $messaging */
                            $messaging = app(LineMessagingClient::class);

                            // สร้าง extraPayload สำหรับ quick reply (ถ้ามี)
                            $extraPayload = [];

                            if ($flowResult->quickReply && is_array($flowResult->quickReply)) {
                                $items = [];

                                foreach ($flowResult->quickReply as $option) {
                                    $label = $option['label'] ?? ($option['text'] ?? null);
                                    $qText = $option['text'] ?? $label;

                                    if (! $label || ! $qText) {
                                        continue;
                                    }

                                    $items[] = [
                                        'type' => 'action',
                                        'action' => [
                                            'type' => 'message',
                                            'label' => $label,
                                            'text' => $qText,
                                        ],
                                    ];
                                }

                                if ($items) {
                                    $extraPayload['quickReply'] = [
                                        'items' => $items,
                                    ];
                                }
                            }

                            try {
                                // ส่งทั้งข้อความ + quick reply (ถ้ามี)
                                $messaging->replyText(
                                    $account,
                                    $replyToken,
                                    $flowResult->replyText,
                                    $extraPayload
                                );
                            } catch (\Throwable $e) {
                                Log::error('[LineWebhook] replyText failed (register flow)', [
                                    'account_id' => $account->id,
                                    'line_message_id' => $message->line_message_id ?? null,
                                    'conversation_id' => $message->line_conversation_id ?? null,
                                    'contact_id' => $message->line_contact_id ?? null,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error('[LineWebhook] register flow error', [
                    'account_id' => $account->id,
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]);
            }

            // ------------------------------------------------------------------
            //  ตรงนี้ยังว่าง เผื่อในอนาคตมี flow อื่น ๆ ที่ใช้ text เดียวกัน
            // ------------------------------------------------------------------
        }

        // ถ้าอยากเก็บ media ด้วย ก็สามารถใช้ ChatService อีก method หนึ่งในอนาคต
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
            'account_id' => $account->id,
            'line_user_id' => $userId,
            'contact_id' => $contact->id,
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
            'account_id' => $account->id,
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
        $data = Arr::get($event, 'postback.data');
        $params = Arr::get($event, 'postback.params', []);

        Log::info('[LineWebhook] postback event', [
            'account_id' => $account->id,
            'line_user_id' => $userId,
            'data' => $data,
            'params' => $params,
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
            'event' => $event,
        ]);
    }

    /**
     * กรณี event type แปลก ๆ หรือไม่รู้จัก
     */
    protected function handleUnknownEvent(LineAccount $account, array $event, ?LineWebhookLog $log = null): void
    {
        Log::warning('[LineWebhook] unknown event type', [
            'account_id' => $account->id,
            'event' => $event,
        ]);
    }

    /**
     * ส่งข้อความต้อนรับเมื่อเป็นข้อความ inbound แรกของห้อง
     *
     * - ดึง template แบบ JSON (version + messages) จาก LineTemplateService
     * - ให้ LineTemplateService แปลง JSON → LINE messages (text + image)
     * - ส่งด้วย pushMessages()
     * - บันทึกทุกข้อความลง line_messages
     */
    protected function handleWelcomeForFirstMessage(
        LineAccount $account,
        array $event,
        LineMessage $inbound
    ): void {
        $lineUserId = Arr::get($event, 'source.userId');
        if (! $lineUserId) {
            return;
        }

        // ให้แน่ใจว่ามี relation ครบ
        $inbound->loadMissing('conversation', 'contact');

        $conversation = $inbound->conversation;
        $contact = $inbound->contact;

        if (! $conversation || ! $contact) {
            return;
        }

        $displayName = $contact->display_name ?: 'ลูกค้า';

        $templateKey = 'welcome.default';
        $lineMessages = [];

        try {
            /** @var LineTemplateService $templates */
            $templates = app(LineTemplateService::class);

            $lineMessages = $templates->renderMessages($templateKey, [
                'display_name' => $displayName,
                'contact' => $contact,
                'conversation' => $conversation,
            ]);
        } catch (\Throwable $e) {
            Log::error('[LineWebhook] welcome template renderMessages failed', [
                'account_id' => $account->id,
                'key' => $templateKey,
                'error' => $e->getMessage(),
            ]);
        }

        // ถ้า template ไม่มี หรือเรนเดอร์พัง → fallback เป็นข้อความ text ธรรมดา
        if (! is_array($lineMessages) || empty($lineMessages)) {
            $fallback = 'สวัสดีค่ะ '.$displayName.' 🎉'."\n"
                .'หากต้องการสอบถามข้อมูลเพิ่มเติม สามารถพิมพ์ถามทีมงานได้เลยนะคะ';

            $lineMessages = [
                [
                    'type' => 'text',
                    'text' => $fallback,
                ],
            ];
        }

        // ------------------------------------------------------------------
        // ใส่ quick reply "สมัคร" / "ทางเข้าเล่น"
        //  - สมัคร: ส่งข้อความ "สมัคร"
        //  - ทางเข้าเล่น: เปิด URL (type = uri)
        // ------------------------------------------------------------------

        // ดึง URL ทางเข้าเล่นจาก config (ปรับตามโปรเจกต์จริงได้เลย)
        $playUrl = UrlHelper::loginUrl();

        $quickReplyItems = [];

        // ปุ่ม สมัคร (ส่งข้อความกลับ)
        $quickReplyItems[] = [
            'type' => 'action',
            'action' => [
                'type' => 'message',
                'label' => 'สมัคร',
                'text' => 'สมัคร',
            ],
        ];

        // ปุ่ม ทางเข้าเล่น (เปิดเว็บ)
        if (! empty($playUrl)) {
            $quickReplyItems[] = [
                'type' => 'action',
                'action' => [
                    'type' => 'uri',
                    'label' => 'ทางเข้าเล่น',
                    'uri' => $playUrl,
                ],
            ];
        }

        if (! empty($quickReplyItems)) {
            for ($i = count($lineMessages) - 1; $i >= 0; $i--) {
                $type = $lineMessages[$i]['type'] ?? null;

                // เผื่ออนาคต: เปิดให้หลาย type รองรับ quickReply
                if (in_array($type, ['text', 'image', 'flex', 'template', 'location', 'sticker'], true)) {
                    $lineMessages[$i]['quickReply'] = [
                        'items' => $quickReplyItems,
                    ];
                    break;
                }
            }
        }


        /** @var LineMessagingClient $messaging */
        $messaging = app(LineMessagingClient::class);

        // ใช้ pushMessages เพื่อรองรับ text + image หลายข้อความ
        $messaging->pushMessages($account, $lineUserId, $lineMessages);

        // เก็บข้อความต้อนรับลง line_messages เพื่อให้เห็นในประวัติ chat
        foreach ($lineMessages as $msg) {
            try {
                $type = $msg['type'] ?? 'text';

                // เตรียม payload ให้ตรง format ที่หน้า admin ใช้
                $payload = null;

                if ($type !== 'text') {
                    if ($type === 'image') {
                        $orig = $msg['originalContentUrl'] ?? null;
                        $prev = $msg['previewImageUrl'] ?? $orig;

                        // สร้าง payload.message ตาม format เดิมของระบบ
                        $payloadMessage = [
                            'type' => 'image',
                            'contentUrl' => $orig,
                            'previewUrl' => $prev,
                            'originalContentUrl' => $orig,
                            'previewImageUrl' => $prev,
                        ];

                        // merge ทับด้วย mapping มาตรฐานหาก msg มี field อื่น
                        $payloadMessage = array_merge($msg, $payloadMessage);

                        $payload = [
                            'message' => $payloadMessage,
                        ];
                    } else {
                        // type อื่น ๆ ที่ไม่ใช่ text → wrap ใส่ message ตรง ๆ
                        $payload = [
                            'message' => $msg,
                        ];
                    }
                }

                LineMessage::create([
                    'line_conversation_id' => $conversation->id,
                    'line_account_id' => $account->id,
                    'line_contact_id' => $contact->id,
                    'direction' => 'outbound',
                    'source' => 'bot',
                    'type' => $type,
                    'line_message_id' => null,
                    'text' => $type === 'text' ? ($msg['text'] ?? null) : null,
                    'payload' => $payload,
                    'meta' => null,
                    'sender_employee_id' => null,
                    'sender_bot_key' => 'welcome',
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::error('[LineWebhook] store bot message failed (welcome)', [
                    'account_id' => $account->id,
                    'conversation_id' => $conversation->id ?? null,
                    'contact_id' => $contact->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
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
