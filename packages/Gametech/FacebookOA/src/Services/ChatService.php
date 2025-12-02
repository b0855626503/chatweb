<?php

namespace Gametech\FacebookOA\Services;

use Gametech\CenterOA\Services\TranslationService;
use Gametech\FacebookOA\Events\FacebookOAChatConversationUpdated;
use Gametech\FacebookOA\Events\FacebookOAChatMessageReceived;
use Gametech\FacebookOA\Models\FacebookAccount;
use Gametech\FacebookOA\Models\FacebookContact;
use Gametech\FacebookOA\Models\FacebookConversation;
use Gametech\FacebookOA\Models\FacebookMessage;
use Gametech\FacebookOA\Models\FacebookWebhookLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChatService
{
    /**
     * Facebook Messaging client สำหรับยิง/ดึงข้อมูลจาก Facebook
     */
    protected FacebookMessagingClient $messagingClient;

    protected TranslationService $translator;

    public function __construct(FacebookMessagingClient $messagingClient, TranslationService $translator)
    {
        $this->messagingClient = $messagingClient;
        $this->translator = $translator;
    }

    /**
     * เวอร์ชันหลัก (ใช้ตอนรับ webhook message จาก Facebook)
     * รองรับ message ทั้งแบบ text + attachment ต่าง ๆ
     *
     * รูปแบบ event (ภายใน messaging):
     * [
     *   'sender' => ['id' => PSID],
     *   'recipient' => ['id' => PAGE_ID],
     *   'timestamp' => 1234567890,
     *   'message' => [
     *       'mid' => 'm_...',
     *       'text' => '...',
     *       'attachments' => [...],
     *       'is_echo' => true|false,
     *   ],
     * ]
     */
    public function handleIncomingMessage(
        FacebookAccount $account,
        array $event,
        ?FacebookWebhookLog $log = null
    ): FacebookMessage {
        $psid         = Arr::get($event, 'sender.id');
        $message      = Arr::get($event, 'message', []);
        $messageMid   = Arr::get($message, 'mid');
        $messageText  = Arr::get($message, 'text');
        $attachments  = Arr::get($message, 'attachments', []);
        $timestamp    = Arr::get($event, 'timestamp'); // sec (ไม่ใช่ ms)
        $isEcho       = (bool) Arr::get($message, 'is_echo', false);

        // map type แบบง่าย
        $messageType = 'unknown';
        if ($messageText && empty($attachments)) {
            $messageType = 'text';
        } elseif (! empty($attachments)) {
            $firstType   = Arr::get($attachments, '0.type');
            $messageType = $firstType ?: 'attachment';
        }

        $sentAtCarbon = $timestamp
            ? now()->setTimestamp((int) $timestamp)
            : now();

        return DB::transaction(function () use (
            $account,
            $event,
            $psid,
            $messageMid,
            $messageType,
            $messageText,
            $sentAtCarbon,
            $isEcho,
            $log
        ) {
            // 1) contact
            $contact = $this->getOrCreateContact($account, $psid);

            // ยังไม่มีชื่อ/รูป ลองไปดึง profile จาก Graph API
            if (empty($contact->name) && empty($contact->avatar_url)) {
                try {
                    $contact = $this->updateContactProfile($account, $psid);
                } catch (\Throwable $e) {
                    // กัน error ไม่ให้พัง flow
                }
            }

            // 2) conversation
            $conversation = $this->getOrCreateConversation($account, $contact);

            // 3) direction/source สำหรับ Facebook:
            //    - is_echo = true  → เราเป็นคนส่ง (outbound, source=page)
            //    - is_echo = false → ลูกค้าส่ง (inbound, source=user)
            $direction = $isEcho ? 'outbound' : 'inbound';
            $source    = $isEcho ? 'page' : 'user';

            /** @var FacebookMessage $message */
            $message = FacebookMessage::create([
                'facebook_conversation_id' => $conversation->id,
                'facebook_account_id'      => $account->id,
                'facebook_contact_id'      => $contact->id,
                'direction'                => $direction,
                'sender_type'              => $source,
                'sender_id'                => $psid,
                'sender_employee_id'       => null,
                'message_mid'              => $messageMid,
                'seq'                      => null,
                'type'                     => $messageType ?: 'text',
                'text'                     => $messageType === 'text' ? $messageText : null,
                'payload'                  => $event,
                'is_echo'                  => $isEcho,
                'is_read'                  => false,
                'read_at'                  => null,
                'delivery_status'          => null,
                'error_code'               => null,
                'error_message'            => null,
                'language'                 => null,
                'sent_at'                  => $sentAtCarbon,
            ]);

            // 3.1 ถ้าเป็นรูป → พยายามเติม contentUrl/previewUrl จาก attachment URL
            if ($messageType === 'image') {
                $this->attachImageContentIfNeeded($account, $message);
            }

            // 3.2 ถ้าเป็นข้อความ text inbound และเปิดใช้ translation → detect language + แปลให้ agent อ่าน
            if ($messageType === 'text' && ! $isEcho) {
                $this->handleInboundTextLanguageAndTranslation($conversation, $contact, $message);
            }

            // 4) update conversation summary
            $conversation->last_message_preview = $this->buildPreviewText($message);
            $conversation->last_message_at      = $sentAtCarbon;

            // นับ unread เฉพาะข้อความ inbound ของลูกค้าเท่านั้น
            if ($direction === 'inbound') {
                $conversation->unread_count = $conversation->unread_count + 1;
            }

            if ($conversation->status === null) {
                $conversation->status = 'open';
            }
            $conversation->save();

            // 5) update contact last_seen เฉพาะ inbound
            if ($direction === 'inbound') {
                $contact->last_seen_at = $sentAtCarbon;
                $contact->save();
            }

            // 6) ผูก log (ถ้ามี)
            if ($log) {
                $log->facebook_account_id      = $account->id;
                $log->facebook_conversation_id = $conversation->id;
                $log->facebook_contact_id      = $contact->id;
                $log->facebook_message_id      = $message->id ?? null;
                $log->delivery_status          = 'processed';
                $log->received_at              = $log->received_at ?? now();
                $log->save();
            }

            // 7) reload relation
            $conversation = $conversation->fresh(['contact', 'account']);

            $message->setRelation('conversation', $conversation);
            $message->setRelation('contact', $contact);

            // 8) broadcast realtime ให้หน้าแอดมิน
            DB::afterCommit(function () use ($conversation, $message) {
                event(new FacebookOAChatMessageReceived(
                    $conversation,
                    $message
                ));

                event(new FacebookOAChatConversationUpdated($conversation));
            });

            return $message;
        });
    }

    /**
     * ใช้ตอนฝั่งแอดมินตอบ (outbound จาก agent) - TEXT
     */
    public function createOutboundMessageFromAgent(
        FacebookConversation $conversation,
        string $text,
        int $employeeId,
        ?array $meta = null
    ): FacebookMessage {
        // เตรียมข้อมูลแปล สำหรับข้อความขาออก (แต่ไม่เปลี่ยน text เดิม)
        $targetLang  = 'th';
        $translation = null;

        if ($this->translator->isEnabled()) {
            $targetLang = $this->translator->resolveTargetLanguage(
                $conversation->outgoing_language,
                optional($conversation->contact)->preferred_language,
                'th'
            );

            if ($targetLang !== 'th') {
                $translation = $this->translator->translate($text, $targetLang, 'th');
            }
        }

        $now = now();

        return DB::transaction(function () use ($conversation, $text, $employeeId, $meta, $now, $translation, $targetLang) {
            $metaPayload = $meta ?? [];
            if (! is_array($metaPayload)) {
                $metaPayload = (array) $metaPayload;
            }

            if ($translation) {
                $metaPayload['translation_outbound'] = [
                    'provider'        => $translation['provider'] ?? null,
                    'original_text'   => $translation['original_text'] ?? $text,
                    'translated_text' => $translation['translated_text'] ?? $text,
                    'source_language' => $translation['source'] ?? 'th',
                    'target_language' => $translation['target'] ?? $targetLang,
                ];
            }

            /** @var FacebookMessage $message */
            $message = FacebookMessage::create([
                'facebook_conversation_id' => $conversation->id,
                'facebook_account_id'      => $conversation->facebook_account_id,
                'facebook_contact_id'      => $conversation->facebook_contact_id,
                'direction'                => 'outbound',
                'sender_type'              => 'agent',
                'sender_id'                => null,
                'sender_employee_id'       => $employeeId,
                'message_mid'              => null,
                'seq'                      => null,
                'type'                     => 'text',
                'text'                     => $text,
                'payload'                  => null,
                'is_echo'                  => false,
                'is_read'                  => false,
                'read_at'                  => null,
                'delivery_status'          => null,
                'error_code'               => null,
                'error_message'            => null,
                'language'                 => null,
                'sent_at'                  => $now,
                'meta'                     => $metaPayload,
            ]);

            $conversation->last_message_preview = $this->buildPreviewText($message);
            $conversation->last_message_at      = $now;
            $conversation->unread_count         = 0;
            $conversation->save();

            DB::afterCommit(function () use ($conversation) {
                event(new FacebookOAChatConversationUpdated(
                    $conversation->fresh(['contact', 'account']) ?? $conversation
                ));
            });

            return $message;
        });
    }

    /**
     * ใช้ตอนฝั่งแอดมินส่ง "รูป" ออกไป
     * (เก็บเฉพาะฝั่ง DB; การยิงไป Facebook ให้ให้ service อื่นทำต่อ)
     */
    public function createOutboundImageFromAgent(
        FacebookConversation $conversation,
        UploadedFile $file,
        int $employeeId,
        ?array $meta = null
    ): FacebookMessage {
        $now = now();

        return DB::transaction(function () use ($conversation, $file, $employeeId, $meta, $now) {
            $path = $file->store('facebook-oa/images', 'public');
            $url  = Storage::disk('public')->url($path);

            $payload = [
                'message' => [
                    'type'   => 'image',
                    'url'    => $url,
                    'source' => 'agent_upload',
                ],
            ];

            /** @var FacebookMessage $message */
            $message = FacebookMessage::create([
                'facebook_conversation_id' => $conversation->id,
                'facebook_account_id'      => $conversation->facebook_account_id,
                'facebook_contact_id'      => $conversation->facebook_contact_id,
                'direction'                => 'outbound',
                'sender_type'              => 'agent',
                'sender_id'                => null,
                'sender_employee_id'       => $employeeId,
                'message_mid'              => null,
                'seq'                      => null,
                'type'                     => 'image',
                'text'                     => null,
                'payload'                  => $payload,
                'is_echo'                  => false,
                'is_read'                  => false,
                'read_at'                  => null,
                'delivery_status'          => null,
                'error_code'               => null,
                'error_message'            => null,
                'language'                 => null,
                'sent_at'                  => $now,
                'meta'                     => $meta,
            ]);

            $conversation->last_message_preview = $this->buildPreviewText($message);
            $conversation->last_message_at      = $now;
            $conversation->unread_count         = 0;
            $conversation->save();

            DB::afterCommit(function () use ($conversation) {
                event(new FacebookOAChatConversationUpdated(
                    $conversation->fresh(['contact', 'account']) ?? $conversation
                ));
            });

            return $message;
        });
    }

    /**
     * ใช้ตอนฝั่งแอดมินส่ง Quick Reply / Template ออกไป
     * (ข้อความที่มาจาก FacebookTemplate + render แล้ว)
     */
    public function createOutboundQuickReplyFromAgent(
        FacebookConversation $conversation,
        string $previewText,
        int $employeeId,
        array $payload,
        ?array $meta = null
    ): FacebookMessage {
        $now = now();

        return DB::transaction(function () use ($conversation, $previewText, $employeeId, $payload, $meta, $now) {
            $metaPayload = $meta ?? [];
            if (! is_array($metaPayload)) {
                $metaPayload = (array) $metaPayload;
            }

            /** @var FacebookMessage $message */
            $message = FacebookMessage::create([
                'facebook_conversation_id' => $conversation->id,
                'facebook_account_id'      => $conversation->facebook_account_id,
                'facebook_contact_id'      => $conversation->facebook_contact_id,
                'direction'                => 'outbound',
                'sender_type'              => 'quick_reply',
                'sender_id'                => null,
                'sender_employee_id'       => $employeeId,
                'message_mid'              => null,
                'seq'                      => null,
                'type'                     => 'text', // หลังบ้านให้แสดงเป็น bubble ข้อความ
                'text'                     => $previewText,
                'payload'                  => $payload,
                'is_echo'                  => false,
                'is_read'                  => false,
                'read_at'                  => null,
                'delivery_status'          => null,
                'error_code'               => null,
                'error_message'            => null,
                'language'                 => null,
                'sent_at'                  => $now,
                'meta'                     => $metaPayload,
            ]);

            $conversation->last_message_preview = $this->buildPreviewText($message);
            $conversation->last_message_at      = $now;
            $conversation->unread_count         = 0;
            $conversation->save();

            DB::afterCommit(function () use ($conversation) {
                event(new FacebookOAChatConversationUpdated(
                    $conversation->fresh(['contact', 'account']) ?? $conversation
                ));
            });

            return $message;
        });
    }

    /**
     * ดึง profile จาก Facebook แล้วอัปเดตลง FacebookContact
     */
    public function updateContactProfile(FacebookAccount $account, string $psid): FacebookContact
    {
        $contact = $this->getOrCreateContact($account, $psid);

        $result = $this->messagingClient->getProfile($account, $psid);

        if (! ($result['success'] ?? false)) {
            return $contact;
        }

        $body = $result['body'] ?? [];

        $contact->name        = $body['name'] ?? $contact->name;
        $contact->first_name  = $body['first_name'] ?? $contact->first_name;
        $contact->last_name   = $body['last_name'] ?? $contact->last_name;
        $contact->avatar_url  = $body['profile_pic'] ?? $contact->avatar_url;
        $contact->locale      = $body['locale'] ?? $contact->locale;
        $contact->timezone    = (string) ($body['timezone'] ?? $contact->timezone);
        $contact->gender      = $body['gender'] ?? $contact->gender;
        $contact->last_seen_at = $contact->last_seen_at ?? now();
        $contact->save();

        return $contact;
    }

    /**
     * หา/สร้าง contact จาก facebook_account + psid
     */
    public function getOrCreateContact(FacebookAccount $account, string $psid): FacebookContact
    {
        /** @var FacebookContact $contact */
        $contact = FacebookContact::where('facebook_account_id', $account->id)
            ->where('psid', $psid)
            ->first();

        if (! $contact) {
            $contact = FacebookContact::create([
                'facebook_account_id' => $account->id,
                'psid'                => $psid,
                'name'                => null,
                'first_name'          => null,
                'last_name'           => null,
                'avatar_url'          => null,
                'locale'              => null,
                'timezone'            => null,
                'gender'              => null,
                'preferred_language'  => null,
                'member_id'           => null,
                'member_username'     => null,
                'member_mobile'     => null,
                'last_seen_at'        => null,
                'blocked_at'          => null,
            ]);
        }

        return $contact;
    }

    /**
     * หา/สร้าง conversation สำหรับ contact นี้ในเพจนี้
     * ใช้ห้องเดิมทุกกรณีที่ยังไม่ปิด (open/assigned/null)
     */
    protected function getOrCreateConversation(FacebookAccount $account, FacebookContact $contact): FacebookConversation
    {
        /** @var FacebookConversation $conversation */
        $conversation = FacebookConversation::where('facebook_account_id', $account->id)
            ->where('facebook_contact_id', $contact->id)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereIn('status', ['open', 'assigned']);
            })
            ->orderByDesc('id')
            ->first();

        if (! $conversation) {
            $conversation = FacebookConversation::create([
                'facebook_account_id' => $account->id,
                'facebook_contact_id' => $contact->id,
                'status'              => 'open',
                'last_message_preview'=> null,
                'last_message_at'     => null,
                'unread_count'        => 0,
                'assigned_employee_id'=> null,
                'locked_by_employee_id'=> null,
            ]);
        }

        return $conversation;
    }

    /**
     * helper: เติม contentUrl/previewUrl จาก attachment URL ถ้ายังไม่มี
     */
    protected function attachImageContentIfNeeded(FacebookAccount $account, FacebookMessage $message): void
    {
        try {
            $payload = $message->payload ?? [];
            $msgPayload = $payload['message'] ?? null;

            // ใน Facebook raw event จริง ๆ message อยู่ระดับ top
            // แต่เรายอมรับทั้งสองแบบ: payload['message'] หรือ payload['message']['attachments']
            if (! is_array($msgPayload)) {
                $msgPayload = $payload['message'] ?? Arr::get($payload, 'message', null);
            }

            $attachments = Arr::get($payload, 'message.attachments', []);

            if (! is_array($attachments) || empty($attachments)) {
                return;
            }

            $first = $attachments[0] ?? null;
            if (! is_array($first)) {
                return;
            }

            $url = Arr::get($first, 'payload.url');
            if (! $url) {
                return;
            }

            // ถ้า message payload ยังไม่มี url กลาง สำหรับ frontend ให้เติมเข้าไป
            $payload['message']['contentUrl'] = $payload['message']['contentUrl'] ?? $url;
            $payload['message']['previewUrl'] = $payload['message']['previewUrl'] ?? $url;

            $message->payload = $payload;
            $message->save();
        } catch (\Throwable $e) {
            \Log::channel('facebook_oa')->error('[FacebookChat] exception ขณะจัดการ image payload', [
                'message_id' => $message->id,
                'message_mid'=> $message->message_mid,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * จัดการภาษา/การแปลสำหรับข้อความ inbound แบบ text
     * - detect language
     * - อัปเดต language fields บน contact / conversation
     * - เก็บผลแปลลง meta.translation_inbound
     */
    protected function handleInboundTextLanguageAndTranslation(
        FacebookConversation $conversation,
        FacebookContact $contact,
        FacebookMessage $message
    ): void {
        if (! $this->translator->isEnabled()) {
            return;
        }

        $text = trim((string) $message->text);
        if ($text === '') {
            return;
        }

        $targetLang = 'th';

        $result = $this->translator->translate($text, $targetLang, null);

        $detected = $result['detected_source'] ?? $result['source'] ?? null;

        if ($detected) {
            $contact->preferred_language = $contact->preferred_language ?: $detected;
            $contact->save();

            $conversation->incoming_language = $detected;

            if (empty($conversation->outgoing_language) && $detected !== $targetLang) {
                $conversation->outgoing_language = $detected;
            }
            $conversation->save();
        }

        $meta = $message->meta ?? [];
        if (! is_array($meta)) {
            $meta = $meta ? (array) $meta : [];
        }

        $meta['translation_inbound'] = [
            'provider'               => $result['provider'] ?? null,
            'original_text'          => $result['original_text'] ?? $text,
            'translated_text'        => $result['translated_text'] ?? $text,
            'source_language'        => $result['source'] ?? null,
            'detected_source'        => $detected,
            'target_language'        => $result['target'] ?? $targetLang,
            'for_agent_display_lang' => $targetLang,
        ];

        $message->meta = $meta;
        $message->save();
    }

    /**
     * สร้างข้อความ preview ให้ใช้ใน list conversation
     */
    protected function buildPreviewText(FacebookMessage $message): string
    {
        if ($message->type === 'text' && $message->text) {
            $text = $message->text;

            return mb_strimwidth($text, 0, 40, '...');
        }

        return '['.$message->type.']';
    }

    public function markConversationAsRead(FacebookConversation $conversation): void
    {
        $conversation->unread_count = 0;
        $conversation->save();

        DB::afterCommit(function () use ($conversation) {
            event(new FacebookOAChatConversationUpdated(
                $conversation->fresh(['contact', 'account']) ?? $conversation
            ));
        });
    }

    /**
     * ปิดห้องสนทนา
     */
    public function closeConversation(FacebookConversation $conversation, ?int $employeeId = null): void
    {
        $conversation->status                = 'closed';
        $conversation->closed_at             = now();
        $conversation->closed_by_employee_id = $employeeId;
        $conversation->unread_count          = 0;
        $conversation->save();

        DB::afterCommit(function () use ($conversation) {
            event(new FacebookOAChatConversationUpdated(
                $conversation->fresh(['contact', 'account']) ?? $conversation
            ));
        });
    }
}
