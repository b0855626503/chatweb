<?php

namespace Gametech\LineOa\Services;

use Gametech\LineOa\Models\LineAccount;
use Gametech\LineOa\Models\LineContact;
use Gametech\LineOa\Models\LineConversation;
use Gametech\LineOa\Models\LineMessage;
use Gametech\LineOa\Models\LineWebhookLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ChatService
{
    /**
     * จัดการข้อความ inbound จาก LINE (ข้อความ text จากลูกค้า)
     *
     * @param  array  $event  event จาก LINE
     * @param  LineWebhookLog|null  $log  log record (optional)
     */
    public function handleIncomingMessage(LineAccount $account, array $event, ?LineWebhookLog $log = null): LineMessage
    {
        $userId = Arr::get($event, 'source.userId');       // line userId
        $messageId = Arr::get($event, 'message.id');
        $messageType = Arr::get($event, 'message.type');
        $text = Arr::get($event, 'message.text');
        $sentAt = Arr::get($event, 'timestamp');

        // timestamp จาก LINE เป็น ms => แปลงเป็น sec
        $sentAtCarbon = $sentAt ? now()->setTimestampMs($sentAt) : now();

        return DB::transaction(function () use (
            $account,
            $userId,
            $messageId,
            $messageType,
            $text,
            $sentAtCarbon,
            $event
        ) {
            // 1) หา/สร้าง contact
            $contact = $this->getOrCreateContact($account, $userId);

            // 2) หา/สร้าง conversation
            $conversation = $this->getOrCreateConversation($account, $contact);

            // 3) สร้าง message (inbound)
            $message = LineMessage::create([
                'line_conversation_id' => $conversation->id,
                'line_account_id' => $account->id,
                'line_contact_id' => $contact->id,
                'direction' => 'inbound',
                'source' => 'user',
                'type' => $messageType ?? 'text',
                'line_message_id' => $messageId,
                'text' => $messageType === 'text' ? $text : null,
                'payload' => $event,
                'meta' => null,
                'sender_employee_id' => null,
                'sender_bot_key' => null,
                'sent_at' => $sentAtCarbon,
            ]);

            // 4) อัปเดต conversation summary
            $conversation->last_message_preview = $this->buildPreviewText($message);
            $conversation->last_message_at = $sentAtCarbon;
            $conversation->unread_count = $conversation->unread_count + 1;
            $conversation->status = 'open';
            $conversation->save();

            // 5) อัปเดต last_seen_at ของ contact
            $contact->last_seen_at = $sentAtCarbon;
            $contact->save();

            return $message;
        });
    }

    /**
     * ใช้ตอนฝั่งแอดมินตอบ (outbound จาก agent)
     *
     * NOTE: ตอนทำ UI หลังบ้านเราจะเรียก method นี้
     */
    public function createOutboundMessageFromAgent(
        LineConversation $conversation,
        string $text,
        int $employeeId,
        ?array $meta = null
    ): LineMessage {
        $now = now();

        return DB::transaction(function () use ($conversation, $text, $employeeId, $meta, $now) {
            $message = LineMessage::create([
                'line_conversation_id' => $conversation->id,
                'line_account_id' => $conversation->line_account_id,
                'line_contact_id' => $conversation->line_contact_id,
                'direction' => 'outbound',
                'source' => 'agent',
                'type' => 'text',
                'line_message_id' => null, // จะมาอัปเดตทีหลังถ้าจำเป็น
                'text' => $text,
                'payload' => null,
                'meta' => $meta,
                'sender_employee_id' => $employeeId,
                'sender_bot_key' => null,
                'sent_at' => $now,
            ]);

            // อัปเดต conversation
            $conversation->last_message_preview = $this->buildPreviewText($message);
            $conversation->last_message_at = $now;
            // พนักงานตอบแล้ว -> unread_count = 0 หรือแล้วแต่ policy
            $conversation->unread_count = 0;
            $conversation->save();

            return $message;
        });
    }

    /**
     * หา/สร้าง contact จาก line_account + line_user_id
     */
    protected function getOrCreateContact(LineAccount $account, string $lineUserId): LineContact
    {
        /** @var LineContact $contact */
        $contact = LineContact::where('line_account_id', $account->id)
            ->where('line_user_id', $lineUserId)
            ->first();

        if (! $contact) {
            $contact = LineContact::create([
                'line_account_id' => $account->id,
                'line_user_id' => $lineUserId,
                'display_name' => null,
                'picture_url' => null,
                'status_message' => null,
                'member_id' => null,
                'member_username' => null,
                'member_mobile' => null,
                'tags' => [],
                'last_seen_at' => null,
                'blocked_at' => null,
            ]);
        }

        return $contact;
    }

    /**
     * หา/สร้าง conversation สำหรับ contact นี้ใน OA นี้
     *
     * ตอนนี้ใช้ policy ง่าย ๆ: 1 contact ต่อ 1 open conversation
     */
    protected function getOrCreateConversation(LineAccount $account, LineContact $contact): LineConversation
    {
        /** @var LineConversation $conversation */
        $conversation = LineConversation::where('line_account_id', $account->id)
            ->where('line_contact_id', $contact->id)
            ->where('status', 'open')
            ->first();

        if (! $conversation) {
            $conversation = LineConversation::create([
                'line_account_id' => $account->id,
                'line_contact_id' => $contact->id,
                'status' => 'open',
                'last_message_preview' => null,
                'last_message_at' => null,
                'unread_count' => 0,
                'assigned_employee_id' => null,
                'locked_by_employee_id' => null,
            ]);
        }

        return $conversation;
    }

    /**
     * สร้างข้อความ preview ให้ใช้ใน list conversation
     */
    protected function buildPreviewText(LineMessage $message): string
    {
        if ($message->type === 'text' && $message->text) {
            $text = $message->text;

            // ตัดความยาวไม่ให้ยาวเกินไป
            return mb_strimwidth($text, 0, 100, '...');
        }

        // สำหรับประเภทอื่น ๆ เช่น image/sticker/template
        return '['.$message->type.']';
    }
}
