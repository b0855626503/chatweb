<?php

namespace Gametech\FacebookOA\Services;

use Gametech\FacebookOA\Models\FacebookAccount;
use Gametech\FacebookOA\Models\FacebookMessage;
use Gametech\FacebookOA\Models\FacebookWebhookLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class FacebookWebhookService
{
    protected ChatService $chat;

    public function __construct(ChatService $chat)
    {
        $this->chat = $chat;
    }

    /**
     * จุดเริ่มต้นในการประมวลผล payload จาก Facebook Webhook ทั้งก้อน
     *
     * ตามสเปกของ Facebook:
     * - object = "page"
     * - entry[] แต่ละ entry มี messaging[]
     *
     * @param  array  $payload  JSON decode จาก body
     */
    public function handle(FacebookAccount $account, array $payload, ?FacebookWebhookLog $log = null): void
    {
        Log::channel('facebook_oa')->warning('[FacebookWebhook] unsupported object type');

        $object = $payload['object'] ?? null;

        if ($object !== 'page') {
            Log::channel('facebook_oa')->warning('[FacebookWebhook] unsupported object type', [
                'account_id' => $account->id,
                'object' => $object,
                'log_id' => $log?->id,
            ]);

            return;
        }

        $entries = $payload['entry'] ?? [];

        if (empty($entries)) {
            Log::channel('facebook_oa')->info('[FacebookWebhook] empty entries', [
                'account_id' => $account->id,
                'log_id' => $log?->id,
            ]);

            return;
        }

        foreach ($entries as $entry) {
            $pageId = Arr::get($entry, 'id');
            $time = Arr::get($entry, 'time');

            $messagingEvents = Arr::get($entry, 'messaging', []);

            if (empty($messagingEvents)) {
                Log::channel('facebook_oa')->info('[FacebookWebhook] empty messaging in entry', [
                    'account_id' => $account->id,
                    'page_id' => $pageId,
                    'time' => $time,
                    'log_id' => $log?->id,
                ]);

                continue;
            }

            foreach ($messagingEvents as $event) {
                try {
                    $this->dispatchMessagingEvent($account, $event, $log, $pageId, $time);
                } catch (\Throwable $e) {
                    Log::channel('facebook_oa')->error('[FacebookWebhook] error on messaging event', [
                        'account_id' => $account->id,
                        'page_id' => $pageId,
                        'time' => $time,
                        'event' => $event,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * แยกประเภท messaging event ของ Facebook
     *
     * รูปแบบหลัก ๆ:
     * - message        → ลูกค้าพิมพ์ หรือ echo ข้อความที่เราส่ง
     * - postback       → กดปุ่ม template
     * - read           → อ่านข้อความแล้ว
     * - delivery       → ส่งถึง/ส่งไม่ถึง
     */
    protected function dispatchMessagingEvent(
        FacebookAccount $account,
        array $event,
        ?FacebookWebhookLog $log,
        ?string $pageId,
        ?int $entryTime
    ): void {
        if (isset($event['message'])) {
            $this->handleMessageEvent($account, $event, $log);

            return;
        }

        if (isset($event['postback'])) {
            $this->handlePostbackEvent($account, $event, $log);

            return;
        }

        if (isset($event['read'])) {
            $this->handleReadEvent($account, $event, $log);

            return;
        }

        if (isset($event['delivery'])) {
            $this->handleDeliveryEvent($account, $event, $log);

            return;
        }

        $this->handleUnknownEvent($account, $event, $log);
    }

    /**
     * message event -> ข้อความจากลูกค้า (หรือ echo ข้อความที่เราเป็นคนส่ง)
     */
    protected function handleMessageEvent(FacebookAccount $account, array $event, ?FacebookWebhookLog $log = null): void
    {
        $isEcho = (bool) Arr::get($event, 'message.is_echo', false);
        $text = Arr::get($event, 'message.text');
        $attachments = Arr::get($event, 'message.attachments', []);

        // mapping type แบบง่าย ๆ:
        // - ถ้ามี text อย่างเดียว → text
        // - ถ้ามี attachments → ใช้ type ของ attachment ตัวแรก
        $messageType = 'unknown';

        if ($text && empty($attachments)) {
            $messageType = 'text';
        } elseif (! empty($attachments)) {
            $firstType = Arr::get($attachments, '0.type');
            $messageType = $firstType ?: 'attachment';
        }

        $mid = Arr::get($event, 'message.mid');

        if ($isEcho) {
            Log::channel('facebook_oa')->info('[FacebookWebhook] receive echo message', [
                'account_id' => $account->id,
                'message_mid' => $mid,
                'message_type' => $messageType,
            ]);
        } elseif ($messageType === 'text') {
            Log::channel('facebook_oa')->info('[FacebookWebhook] receive text message', [
                'account_id' => $account->id,
                'message_mid' => $mid,
            ]);
        } else {
            Log::channel('facebook_oa')->info('[FacebookWebhook] receive non-text message', [
                'account_id' => $account->id,
                'message_mid' => $mid,
                'message_type' => $messageType,
            ]);
        }

        // ไม่ว่า type อะไร ให้เก็บลง DB ผ่าน ChatService.handleIncomingMessage เสมอ
        try {
            /** @var FacebookMessage $message */
            $message = $this->chat->handleIncomingMessage($account, $event, $log);
        } catch (\Throwable $e) {
            Log::channel('facebook_oa')->error('[FacebookWebhook] handleMessageEvent exception', [
                'account_id' => $account->id,
                'message_mid' => $mid,
                'message_type' => $messageType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        // ------------------------------------------------------------------
        //  เช็คว่าเป็นข้อความ inbound แรกของห้องหรือไม่ → ส่งข้อความต้อนรับ (future)
        // ------------------------------------------------------------------
        try {
            $contact = $message->contact ?? null;
            $conversation = $message->conversation ?? null;

            if ($contact && $conversation && $message->direction === 'inbound') {
                $isFirstInbound = ! FacebookMessage::query()
                    ->where('facebook_conversation_id', $conversation->id)
                    ->where('direction', 'inbound')
                    ->where('id', '<', $message->id)
                    ->exists();

                if ($isFirstInbound) {
                    $this->handleWelcomeForFirstMessage($account, $event, $message);
                }
            }
        } catch (\Throwable $e) {
            Log::channel('facebook_oa')->error('[FacebookWebhook] welcome flow error', [
                'account_id' => $account->id,
                'facebook_message_id' => $message->id ?? null,
                'conversation_id' => $message->facebook_conversation_id ?? null,
                'contact_id' => $message->facebook_contact_id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        // ------------------------------------------------------------------
        //  เฉพาะ text ถึงจะโยนเข้า flow อื่น ๆ (เช่น สมัครสมาชิก / keyword automation)
        //  (ตอนนี้ยังไม่ผูก RegisterFlowService ของ FacebookOA โดยตรง → เว้น TODO ไว้ก่อน)
        // ------------------------------------------------------------------
        if ($messageType === 'text') {
            $text = $message->text ?? '';

            // TODO:
            // - future: ทำ RegisterFlowService สำหรับ FacebookOA แล้วเรียกใช้ตรงนี้
            // - ใช้ pattern คล้าย LineOA แต่เปลี่ยนจาก replyToken → PSID + Send API
        }
    }

    /**
     * postback event -> กดปุ่มจาก template / persistent menu
     */
    protected function handlePostbackEvent(FacebookAccount $account, array $event, ?FacebookWebhookLog $log = null): void
    {
        $psid = Arr::get($event, 'sender.id');
        $title = Arr::get($event, 'postback.title');
        $data = Arr::get($event, 'postback.payload'); // Facebook ใช้ชื่อ payload
        $ref = Arr::get($event, 'postback.referral');

        Log::channel('facebook_oa')->info('[FacebookWebhook] postback event', [
            'account_id' => $account->id,
            'psid' => $psid,
            'title' => $title,
            'payload' => $data,
            'referral' => $ref,
        ]);

        // TODO:
        // - parse $data เช่น "action=register_confirm&session_id=123"
        // - ส่งต่อไปยัง flow ที่เกี่ยวข้อง (ถ้ามี)
    }

    /**
     * read event -> ลูกค้าอ่านข้อความแล้ว
     */
    protected function handleReadEvent(FacebookAccount $account, array $event, ?FacebookWebhookLog $log = null): void
    {
        $psid = Arr::get($event, 'sender.id');
        $watermark = Arr::get($event, 'read.watermark');
        $seq = Arr::get($event, 'read.seq');

        Log::channel('facebook_oa')->info('[FacebookWebhook] read event', [
            'account_id' => $account->id,
            'psid' => $psid,
            'watermark' => $watermark,
            'seq' => $seq,
        ]);

        // TODO:
        // - update facebook_messages ที่ sent_at <= watermark ให้ is_read = true, read_at = now()
        // - update unread_count ของ conversation ให้เป็น 0 หรือปรับตาม logic
    }

    /**
     * delivery event -> สถานะส่งข้อความ (delivered / failed)
     */
    protected function handleDeliveryEvent(FacebookAccount $account, array $event, ?FacebookWebhookLog $log = null): void
    {
        $psid = Arr::get($event, 'sender.id');
        $mids = Arr::get($event, 'delivery.mids', []);
        $watermark = Arr::get($event, 'delivery.watermark');
        $seq = Arr::get($event, 'delivery.seq');

        Log::channel('facebook_oa')->info('[FacebookWebhook] delivery event', [
            'account_id' => $account->id,
            'psid' => $psid,
            'mids' => $mids,
            'watermark' => $watermark,
            'seq' => $seq,
        ]);

        // TODO:
        // - mark facebook_messages.delivery_status = 'delivered' ตาม mids
        // - เผื่ออนาคตเก็บ failed status (ต้องดูจาก error ของ Send API)
    }

    /**
     * generic handler สำหรับ event ที่เรายังไม่ได้สนใจมาก หรือรูปแบบแปลก ๆ
     */
    protected function handleUnknownEvent(FacebookAccount $account, array $event, ?FacebookWebhookLog $log = null): void
    {
        Log::channel('facebook_oa')->warning('[FacebookWebhook] unknown messaging event', [
            'account_id' => $account->id,
            'event' => $event,
        ]);
    }

    /**
     * ส่งข้อความต้อนรับเมื่อเป็นข้อความ inbound แรกของห้อง
     *
     * version นี้ตั้งใจทำให้เหมือน LineWebhookService:
     * - มี method ไว้แล้ว
     * - แต่ยัง return ทันที (ปิดการใช้งานไว้ก่อน)
     * - เผื่ออนาคตใช้ FacebookTemplateService + FacebookMessagingClient
     */
    protected function handleWelcomeForFirstMessage(
        FacebookAccount $account,
        array $event,
        FacebookMessage $inbound
    ): void {
        $psid = Arr::get($event, 'sender.id');
        if (! $psid) {
            return;
        }

        // ปิด welcome flow ไว้ก่อน (เหมือนฝั่ง LINE ที่มี return ตัดทิ้ง)

        // ถ้าในอนาคตจะเปิดใช้งาน:
        // - load relation conversation / contact
        // - ดึง template จาก FacebookTemplateService
        // - สร้าง payload messages (text + image)
        // - ส่งด้วย FacebookMessagingClient::sendMessages(...)
        // - บันทึกลง facebook_messages (direction = outbound, source = bot, sender_bot_key = 'welcome')
    }
}
