<?php

namespace Gametech\FacebookOA\Http\Controllers\Admin;

use Gametech\Admin\Http\Controllers\AppBaseController;
use Gametech\FacebookOA\DataTables\TopupDataTable;
use Gametech\FacebookOA\Events\FacebookOAChatConversationUpdated;
use Gametech\FacebookOA\Events\FacebookOAConversationAssigned;
use Gametech\FacebookOA\Events\FacebookOAConversationClosed;
use Gametech\FacebookOA\Events\FacebookOAConversationLocked;
use Gametech\FacebookOA\Events\FacebookOAConversationOpen;
use Gametech\FacebookOA\Models\FacebookContact;
use Gametech\FacebookOA\Models\FacebookConversation;
use Gametech\FacebookOA\Models\FacebookMessage;
use Gametech\FacebookOA\Models\FacebookRegisterSession;
use Gametech\FacebookOA\Models\FacebookTemplate;
use Gametech\FacebookOA\Services\ChatService;
use Gametech\FacebookOA\Services\FacebookMessagingClient;
use Gametech\FacebookOA\Services\RegisterFlowService;
use Gametech\FacebookOA\Support\UrlHelper;
use Gametech\Game\Repositories\GameUserRepository;
use Gametech\Member\Repositories\MemberRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ChatController extends AppBaseController
{
    protected ChatService $chat;

    protected FacebookMessagingClient $facebookMessaging;

    public function __construct(ChatService $chat, FacebookMessagingClient $facebookMessaging)
    {
        $this->chat = $chat;
        $this->facebookMessaging = $facebookMessaging;
    }

    /**
     * แสดงหน้าแชต (Blade + Vue UI)
     */
    public function page(TopupDataTable $topupDataTable)
    {
        // ให้ได้ตัว Html\Builder แบบเดียวกับตอนใช้ ->render()
        $depositTable = $topupDataTable->html();

        return view('admin::module.facebook-oa.index', [
            'depositTable' => $depositTable,
        ]);
    }

    /**
     * ดึง list ห้องแชต (sidebar ซ้าย)
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->get('status', 'open'); // open | closed (UI)
        $accountId = $request->get('account_id');
        $q = trim((string) $request->get('q', ''));
        $perPage = (int) $request->get('per_page', 20);
        $scope = $request->get('scope', 'all'); // all | mine

        $query = FacebookConversation::query()
            ->with([
                'contact.member',
                'account',
                'registerSessions' => function ($q) {
                    $q->where('status', 'in_progress');
                },
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        // ===== filter ตาม OA =====
        if ($accountId) {
            $query->where('facebook_account_id', $accountId);
        }

        // ===== filter ตาม scope =====
        if ($scope === 'mine') {
            $employee = Auth::guard('admin')->user();
            if ($employee) {
                // ให้ตรงกับที่ assign ตอนรับเรื่อง
                $employeeId = $employee->code ?? $employee->id ?? null;
                if ($employeeId) {
                    $query->where('assigned_employee_id', $employeeId);
                }
            }
        }

        // ===== filter ตาม status =====
        if ($status === 'closed') {
            // เคสที่ปิดแล้วเท่านั้น
            $query->where('status', 'closed');

        } else {
            // “ยังไม่ปิดเคส”
            $query->where(function ($qBuilder) {
                $qBuilder->whereNull('status')
                    ->orWhereIn('status', ['open', 'assigned', 'closed']);
            });
        }

        // ===== คำค้นหา =====
        if ($q !== '') {
            $query->whereHas('contact', function ($qQuery) use ($q) {
                $qQuery->where('display_name', 'like', '%'.$q.'%')
                    ->orWhere('member_username', 'like', '%'.$q.'%')
                    ->orWhere('member_mobile', 'like', '%'.$q.'%');
            });
        }

        $paginator = $query->paginate($perPage);

        $data = [
            'data' => $paginator->getCollection()->map(function (FacebookConversation $conv) {
                return [
                    'id' => $conv->id,
                    'status' => $conv->status,
                    'last_message' => $conv->last_message_preview,
                    'last_message_at' => optional($conv->last_message_at)->toIso8601String(),
                    'unread_count' => $conv->unread_count,
                    'is_registering' => $conv->is_registering,
                    // *** ที่ต้องส่งเพิ่ม ***
                    'assigned_employee_id' => $conv->assigned_employee_id,
                    'assigned_employee_name' => $conv->assigned_employee_name,
                    'assigned_at' => optional($conv->assigned_at)->toIso8601String(),

                    'locked_by_employee_id' => $conv->locked_by_employee_id,
                    'locked_by_employee_name' => $conv->locked_by_employee_name,
                    'locked_at' => optional($conv->locked_at)->toIso8601String(),

                    'closed_by_employee_id' => $conv->closed_by_employee_id,
                    'closed_by_employee_name' => $conv->closed_by_employee_name,
                    'closed_at' => optional($conv->closed_at)->toIso8601String(),

                    'facebook_account' => [
                        'id' => $conv->account?->id,
                        'name' => $conv->account?->name,
                    ],
                    'contact' => [
                        'id' => $conv->contact?->id,
                        'display_name' => $conv->contact?->display_name,
                        'member_id' => $conv->contact?->member_id,
                        'member_username' => $conv->contact?->member_username,
                        'member_mobile' => $conv->contact?->member_mobile,
                        'picture_url' => $conv->contact?->picture_url,
                        'member_name' => $conv->contact?->member?->name,
                        'member_bank_name' => $conv->contact?->member?->bank?->name_th,
                        'member_acc_no' => $conv->contact?->member?->acc_no,
                    ],
                ];
            }),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];

        return response()->json($data);
    }

    /**
     * ดึงรายละเอียดห้อง + messages ล่าสุด
     */
    public function show(Request $request, FacebookConversation $conversation): JsonResponse
    {
        $limit = (int) $request->get('limit', 50);
        $beforeId = $request->get('before_id');
        $previousId = $request->get('previous_id');

        $conversation->load([
            'contact.member',
            'account',
            'registerSessions' => function ($q) {
                $q->where('status', 'in_progress');
            },
        ]);

        // ===== เคลียร์ unread ของห้องก่อนหน้า (ถ้ามีส่ง previous_id มา) =====
        if ($previousId && (int) $previousId !== (int) $conversation->id) {
            /** @var \Gametech\FacebookOA\Models\FacebookConversation|null $prevConv */
            $prevConv = FacebookConversation::query()->find($previousId);

            if ($prevConv && $prevConv->unread_count > 0) {
                $prevConv->unread_count = 0;
                $prevConv->save();

                DB::afterCommit(function () use ($prevConv) {
                    $conv = $prevConv->fresh([
                        'contact.member',
                        'account',
                        'registerSessions' => function ($q) {
                            $q->where('status', 'in_progress');
                        },
                    ]) ?? $prevConv;
                    event(new FacebookOAChatConversationUpdated($conv));
                });
            }
        }

        $messagesQuery = FacebookMessage::query()
            ->where('facebook_conversation_id', $conversation->id)
            ->orderByDesc('id');

        if ($beforeId) {
            $messagesQuery->where('id', '<', $beforeId);
        }

        $messages = $messagesQuery
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        // clear unread
        if ($conversation->unread_count > 0) {
            $conversation->unread_count = 0;
            $conversation->save();

            // broadcast ให้ agent คนอื่นเห็นว่า unread เคลียร์แล้ว
            DB::afterCommit(function () use ($conversation) {
                $conv = $conversation->fresh([
                    'contact.member',
                    'account',
                    'registerSessions' => function ($q) {
                        $q->where('status', 'in_progress');
                    },
                ]) ?? $conversation;

                event(new FacebookOAChatConversationUpdated($conv));
            });
        }

        $data = [
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
                'last_message_at' => optional($conversation->last_message_at)->toDateTimeString(),
                'unread_count' => $conversation->unread_count,
                'is_registering' => $conversation->is_registering,
                // *** ส่งเพิ่ม ***
                'assigned_employee_id' => $conversation->assigned_employee_id,
                'assigned_employee_name' => $conversation->assigned_employee_name,
                'assigned_at' => optional($conversation->assigned_at)->toIso8601String(),

                'locked_by_employee_id' => $conversation->locked_by_employee_id,
                'locked_by_employee_name' => $conversation->locked_by_employee_name,
                'locked_at' => optional($conversation->locked_at)->toIso8601String(),

                'closed_by_employee_id' => $conversation->closed_by_employee_id,
                'closed_by_employee_name' => $conversation->closed_by_employee_name,
                'closed_at' => optional($conversation->closed_at)->toIso8601String(),

                'incoming_language' => $conversation->incoming_language,
                'outgoing_language' => $conversation->outgoing_language,

                'facebook_account' => [
                    'id' => $conversation->account?->id,
                    'name' => $conversation->account?->name,
                ],
                'contact' => [
                    'id' => $conversation->contact?->id,
                    'display_name' => $conversation->contact?->display_name,
                    'facebook_user_id' => $conversation->contact?->facebook_user_id,
                    'member_id' => $conversation->contact?->member_id,
                    'member_username' => $conversation->contact?->member_username,
                    'member_mobile' => $conversation->contact?->member_mobile,
                    'picture_url' => $conversation->contact?->picture_url,
                    'blocked_at' => optional($conversation->contact?->blocked_at)->toDateTimeString(),

                    'member_name' => $conversation->contact?->member?->name,
                    'member_bank_name' => $conversation->contact?->member?->bank?->name_th,
                    'member_acc_no' => $conversation->contact?->member?->acc_no,

                    'preferred_language' => $conversation->contact?->preferred_language,
                    'last_detected_language' => $conversation->contact?->last_detected_language,
                ],
            ],
            'messages' => $messages->map(function (FacebookMessage $m) {
                return [
                    'id' => $m->id,
                    'direction' => $m->direction,
                    'source' => $m->source,
                    'type' => $m->type,
                    'text' => $m->text,
                    'sent_at' => optional($m->sent_at)->toIso8601String(),
                    'sender_employee_id' => $m->sender_employee_id,
                    'sender_bot_key' => $m->sender_bot_key,
                    'meta' => $m->meta,
                    'payload' => $m->payload,
                ];
            }),
        ];

        return response()->json($data);
    }

    /**
     * ส่ง TEXT จาก admin
     */
    public function reply(Request $request, FacebookConversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string'],
        ]);

        $text = trim($data['text']);

        if ($text === '') {
            return response()->json([
                'message' => 'ข้อความห้ามว่าง',
            ], 422);
        }

        // 👇 กันส่งข้อความในห้องที่ปิดแล้ว
        if ($conversation->status === 'closed') {
            return response()->json([
                'message' => 'เคสนี้ถูกปิดแล้ว ไม่สามารถส่งข้อความได้',
            ], 409);
        }

        $employee = Auth::guard('admin')->user();
        $employeeId = $employee?->code ?? null;

        if (! $employeeId) {
            return response()->json([
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        //        if ($conversation->locked_by_employee_id && $conversation->locked_by_employee_id != $employeeId) {
        //            return response()->json([
        //                'message' => 'ห้องนี้ถูกล็อกโดย '.($conversation->locked_by_employee_name ?: 'พนักงานคนอื่น').' คุณไม่สามารถตอบได้',
        //            ], 403);
        //        }

        $message = $this->chat->createOutboundMessageFromAgent(
            $conversation,
            $text,
            $employeeId,
            [
                'employee_name' => $employee->user_name ?? null,
            ]
        );

        $conversation->loadMissing(['account', 'contact.member']);
        $account = $conversation->account;
        $contact = $conversation->contact;

        // -------------------------
        // เลือกข้อความที่จะส่งออกไป LINE
        // ถ้ามี meta.translation_outbound → ใช้ translated_text
        // ไม่งั้น fallback เป็น $text เดิม
        // -------------------------
        $facebookText = $text;

        $meta = $message->meta;
        if (is_array($meta)) {
            $outboundTrans = $meta['translation_outbound'] ?? null;

            if (is_array($outboundTrans) && ! empty($outboundTrans['translated_text'])) {
                $facebookText = $outboundTrans['translated_text'];
            }
        }

        if ($account && $contact && $contact->facebook_user_id) {
            $result = $this->facebookMessaging->pushText(
                $account,
                $contact->facebook_user_id,
                $facebookText        // ← เปลี่ยนมาใช้ตัวนี้
            );

            if (! $result['success']) {
                Log::channel('facebook_oa')->warning('[FacebookChat] ส่งข้อความไป LINE ไม่สำเร็จ', [
                    'conversation_id' => $conversation->id,
                    'contact_id' => $contact->id,
                    'error' => $result['error'] ?? null,
                    'status' => $result['status'] ?? null,
                ]);
            }
        } else {
            Log::channel('facebook_oa')->warning('[FacebookChat] ไม่สามารถส่งข้อความไป LINE ได้ (ไม่พบ account/contact/facebook_user_id)', [
                'conversation_id' => $conversation->id,
            ]);
        }

        return response()->json([
            'message' => 'success',
            'data' => [
                'id' => $message->id,
                'direction' => $message->direction,
                'source' => $message->source,
                'type' => $message->type,
                'text' => $message->text,
                'sent_at' => optional($message->sent_at)->toIso8601String(),
                'sender_employee_id' => $message->sender_employee_id,
                'sender_bot_key' => $message->sender_bot_key,
                'meta' => $message->meta,
                'payload' => $message->payload,
            ],
        ]);
    }

    /**
     * ส่ง IMAGE จาก admin
     */
    public function replyImage(Request $request, FacebookConversation $conversation): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'], // 5MB
        ]);

        if ($conversation->status === 'closed') {
            return response()->json([
                'message' => 'เคสนี้ถูกปิดแล้ว ไม่สามารถส่งรูปภาพได้',
            ], 409);
        }

        $file = $request->file('image');

        $employee = Auth::guard('admin')->user();
        $employeeId = $employee?->code ?? null;

        if (! $employeeId) {
            return response()->json([
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        //        if ($conversation->locked_by_employee_id && $conversation->locked_by_employee_id != $employeeId) {
        //            return response()->json([
        //                'message' => 'ห้องนี้ถูกล็อกโดย '.($conversation->locked_by_employee_name ?: 'พนักงานคนอื่น').' คุณไม่สามารถตอบได้',
        //            ], 403);
        //        }

        $message = $this->chat->createOutboundImageFromAgent(
            $conversation,
            $file,
            $employeeId,
            [
                'employee_name' => $employee->user_name ?? null,
            ]
        );

        $payloadMsg = $message->payload['message'] ?? [];
        $originalUrl = $payloadMsg['contentUrl'] ?? null;
        $previewUrl = $payloadMsg['previewUrl'] ?? $originalUrl;

        if ($originalUrl) {
            $originalUrl = url($originalUrl);
        }
        if ($previewUrl) {
            $previewUrl = url($previewUrl);
        }

        $conversation->loadMissing(['account', 'contact.member']);
        $account = $conversation->account;
        $contact = $conversation->contact;

        if ($account && $contact && $contact->facebook_user_id && $originalUrl) {
            $result = $this->facebookMessaging->sendImageMessage(
                $account,
                $contact->facebook_user_id,
                $originalUrl,
                $previewUrl
            );

            if (! $result['success']) {
                Log::channel('facebook_oa')->warning('[FacebookChat] ส่งรูปไป LINE ไม่สำเร็จ', [
                    'conversation_id' => $conversation->id,
                    'contact_id' => $contact->id,
                    'image_url' => $originalUrl,
                    'error' => $result['error'] ?? null,
                    'status' => $result['status'] ?? null,
                ]);
            }
        } else {
            Log::channel('facebook_oa')->warning('[FacebookChat] ไม่สามารถส่งรูปไป LINE ได้ (ไม่พบ account/contact/facebook_user_id หรือ url ว่าง)', [
                'conversation_id' => $conversation->id,
                'image_url' => $originalUrl,
            ]);
        }

        return response()->json([
            'message' => 'success',
            'data' => [
                'id' => $message->id,
                'direction' => $message->direction,
                'source' => $message->source,
                'type' => $message->type,
                'text' => $message->text,
                'sent_at' => optional($message->sent_at)->toIso8601String(),
                'sender_employee_id' => $message->sender_employee_id,
                'sender_bot_key' => $message->sender_bot_key,
                'meta' => $message->meta,
                'payload' => $message->payload,
            ],
        ]);
    }

    /**
     * ส่งข้อความจาก LINE template (รองรับ JSON หลายข้อความ เช่น text + image)
     *
     * POST /admin/facebook-oa/conversations/{conversation}/reply-template
     */
    public function replyTemplate_(Request $request, FacebookConversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer'],
            'vars' => ['array'], // optional: ตัวแปร placeholder
        ]);

        $template = FacebookTemplate::query()
            ->where('id', $data['template_id'])
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return response()->json([
                'message' => 'ไม่พบ template',
            ], 404);
        }

        $employee = Auth::guard('admin')->user();
        $employeeId = $employee?->id ?? null;

        if (! $employeeId) {
            return response()->json([
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        $vars = $data['vars'] ?? [];

        // 👉 1) แปลง template → LINE messages (text + image ครบชุด)
        $facebookMessages = $template->toFacebookMessages($vars);

        if (empty($facebookMessages)) {
            return response()->json([
                'message' => 'template นี้ไม่มีข้อความที่ส่งได้',
            ], 422);
        }

        // 👉 2) บันทึกลง DB เป็น message เดียว (payload เก็บรายละเอียดทั้งหมด)
        $now = now();

        $previewText = null;
        foreach ($facebookMessages as $lm) {
            if ($lm['type'] === 'text' && ! empty($lm['text'])) {
                $previewText = $lm['text'];
                break;
            }
        }

        if (! $previewText) {
            // ถ้าไม่มี text เลย ก็ใช้ type แรก
            $previewText = '['.$facebookMessages[0]['type'].']';
        }

        $message = FacebookMessage::create([
            'facebook_conversation_id' => $conversation->id,
            'facebook_account_id' => $conversation->facebook_account_id,
            'facebook_contact_id' => $conversation->facebook_contact_id,
            'direction' => 'outbound',
            'source' => 'quick_reply',      // 👈 แยกจาก agent manual
            'type' => 'template',           // logical type ในระบบ
            'facebook_message_id' => null,
            'text' => $previewText,         // เอาไว้แสดง preview
            'payload' => [
                'template_id' => $template->id,
                'facebook_messages' => $facebookMessages,
            ],
            'meta' => [
                'employee_name' => $employee->name ?? null,
            ],
            'sender_employee_id' => $employeeId,
            'sender_bot_key' => null,
            'sent_at' => $now,
        ]);

        // อัปเดตสรุปที่ conversation
        $conversation->last_message_preview = $previewText;
        $conversation->last_message_at = $now;
        $conversation->unread_count = 0;
        $conversation->save();

        // 👉 3) ส่งไปที่ LINE จริง ๆ (push หลายข้อความ)
        $account = $conversation->account;
        $contact = $conversation->contact;

        if ($account && $contact && $contact->facebook_user_id) {
            $result = $this->facebookMessaging->pushMessages(
                $account,
                $contact->facebook_user_id,
                $facebookMessages
            );

            if (! $result['success']) {
                Log::channel('facebook_oa')->warning('[FacebookChat] ส่ง template ไป LINE ไม่สำเร็จ', [
                    'conversation_id' => $conversation->id,
                    'contact_id' => $contact->id,
                    'template_id' => $template->id,
                    'error' => $result['error'],
                    'status' => $result['status'],
                ]);
            }
        } else {
            Log::channel('facebook_oa')->warning('[FacebookChat] ไม่สามารถส่ง template ไป LINE ได้ (ไม่พบ account/contact/facebook_user_id)', [
                'conversation_id' => $conversation->id,
                'template_id' => $template->id,
            ]);
        }

        return response()->json([
            'message' => 'success',
            'data' => [
                'id' => $message->id,
                'direction' => $message->direction,
                'source' => $message->source,
                'type' => $message->type,
                'text' => $message->text,
                'sent_at' => optional($message->sent_at)->toDateTimeString(),
                'sender_employee_id' => $message->sender_employee_id,
                'meta' => $message->meta,
                'payload' => $message->payload,
            ],
        ]);
    }

    /**
     * ส่งข้อความจาก LINE template (Quick Reply)
     * รองรับทั้ง text เดียว และ JSON หลายข้อความ (text + image)
     *
     * POST /admin/facebook-oa/conversations/{conversation}/reply-template
     * body: { template_id: int, vars?: { ...placeholders... } }
     */
    public function replyTemplate__(Request $request, FacebookConversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer'],
            'vars' => ['array'],
        ]);

        /** @var \Gametech\Admin\Models\Admin|null $employee */
        $employee = Auth::guard('admin')->user();

        if (! $employee) {
            return response()->json([
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        // ===== 1) หา template =====
        /** @var FacebookTemplate|null $template */
        $template = FacebookTemplate::query()
            ->where('id', $data['template_id'])
            // ถ้าในตารางใช้ชื่อ field ว่า enabled:
            ->where(function ($q) {
                $q->where('enabled', 1)->orWhereNull('enabled');
            })
            ->first();

        if (! $template) {
            return response()->json([
                'message' => 'ไม่พบข้อความด่วนที่เลือก',
            ], 404);
        }

        $conversation->loadMissing([
            'contact.member',
            'contact.member.bank',
        ]);

        $contact = $conversation->contact;
        $member = $contact?->member;
        $bank = $member?->bank;

        $baseVars = [
            'display_name' => $contact->display_name
                ?? $contact->name
                    ?? $contact->facebook_name
                    ?? 'ลูกค้า',
            'username' => $contact->member_username ?? '',
            'member_id' => $contact->member_id ?? '',
            'phone' => $contact->member_mobile ?? '',
            'bank_name' => $bank->name_th ?? '',
            'bank_code' => $bank->shortcode ?? '',
            'account_no' => $member->acc_no ?? '',
            'site_name' => config('app.name', config('app.domain_url')),
            'login_url' => UrlHelper::loginUrl(),
            'support_name' => trim(($employee->name ?? '').' '.($employee->surname ?? '')),
        ];

        // ตัวแปรที่ frontend ส่งมา override ของ base ได้
        $vars = array_merge($baseVars, $data['vars'] ?? []);

        // ===== 3) แปลง template.message -> โครงสร้าง {version, messages[]} =====
        $structured = $this->normalizeTemplateMessage($template->message);

        $items = $structured['messages'] ?? [];
        if (! is_array($items) || ! count($items)) {
            return response()->json([
                'message' => 'template นี้ไม่มีข้อความที่ส่งได้',
            ], 422);
        }

        // ===== 4) render placeholders + แปลงเป็น LINE messages (text / image) =====
        $facebookMessages = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $kind = $item['kind'] ?? 'text';

            if ($kind === 'text') {
                $text = (string) ($item['text'] ?? '');
                $text = $this->applyTemplatePlaceholders($text, $vars);

                if ($text === '') {
                    continue;
                }

                $facebookMessages[] = [
                    'type' => 'text',
                    'text' => $text,
                ];
            } elseif ($kind === 'image') {
                // รองรับ both original/preview หรือ url เดียว
                $original = $item['original'] ?? $item['url'] ?? '';
                $preview = $item['preview'] ?? $original;

                $original = $this->applyTemplatePlaceholders((string) $original, $vars);
                $preview = $this->applyTemplatePlaceholders((string) $preview, $vars);

                if ($original === '') {
                    continue;
                }

                $facebookMessages[] = [
                    'type' => 'image',
                    'originalContentUrl' => $original,
                    'previewImageUrl' => $preview,
                ];
            }
            // TODO: รองรับ kind อื่นในอนาคต เช่น sticker, flex ฯลฯ
        }

        if (! count($facebookMessages)) {
            return response()->json([
                'message' => 'template นี้ไม่มีข้อความที่ส่งได้ หลังแทนตัวแปรแล้ว',
            ], 422);
        }

        // ===== 5) เลือกข้อความ text ตัวแรกไว้เป็น preview ในระบบแชต =====
        $previewText = null;
        foreach ($facebookMessages as $lm) {
            if ($lm['type'] === 'text' && ! empty($lm['text'])) {
                $previewText = $lm['text'];
                break;
            }
        }

        if (! $previewText) {
            // ถ้าไม่มี text เลย ก็ใช้ type ของ message แรก
            $firstType = $facebookMessages[0]['type'] ?? 'message';
            $previewText = '['.$firstType.']';
        }

        // ===== 6) บันทึก 1 record ลง facebook_messages (เก็บ payload ทั้งชุดไว้) =====
        $now = now();

        /** @var FacebookMessage $message */
        $message = FacebookMessage::create([
            'facebook_conversation_id' => $conversation->id,
            'facebook_account_id' => $conversation->facebook_account_id,
            'facebook_contact_id' => $conversation->facebook_contact_id,
            'direction' => 'outbound',
            'source' => 'quick_reply',
            'type' => 'text',   // ให้ UI แสดงเป็น bubble ข้อความ
            'facebook_message_id' => null,
            'text' => $previewText,
            'payload' => [
                'template_id' => $template->id,
                'facebook_messages' => $facebookMessages,
                'vars' => $vars,
            ],
            'meta' => [
                'template_key' => $template->key ?? null,
                'template_title' => $template->title ?? $template->description ?? null,
                'sender_employee_name' => $employee->name ?? null,
            ],
            'sender_employee_id' => $employee->id ?? null,
            'sender_bot_key' => null,
            'sent_at' => $now,
        ]);

        // อัปเดตสรุปที่ conversation
        $conversation->last_message = $previewText;
        $conversation->last_message_at = $now;
        $conversation->last_message_source = 'quick_reply';
        $conversation->unread_count = 0;
        $conversation->save();

        // ===== 7) ส่งไปที่ LINE จริง ๆ =====
        $account = $conversation->account;   // สมมติ relation ตั้งชื่อว่า account
        $contact = $conversation->contact;

        if ($account && $contact && $contact->facebook_user_id) {
            $result = $this->facebookMessaging->pushMessages(
                $account,
                $contact->facebook_user_id,
                $facebookMessages
            );

            if (! ($result['success'] ?? false)) {
                Log::channel('facebook_oa')->warning('[FacebookOA] ส่ง quick reply ไป LINE ไม่สำเร็จ', [
                    'conversation_id' => $conversation->id,
                    'contact_id' => $contact->id ?? null,
                    'template_id' => $template->id,
                    'status' => $result['status'] ?? null,
                    'error' => $result['error'] ?? null,
                ]);
            }
        } else {
            Log::channel('facebook_oa')->warning('[FacebookOA] ไม่สามารถส่ง quick reply ไป LINE ได้ (ไม่พบ account/contact/facebook_user_id)', [
                'conversation_id' => $conversation->id,
                'template_id' => $template->id,
            ]);
        }

        return response()->json([
            'data' => $message,
        ]);
    }

    public function replyTemplate____(Request $request, FacebookConversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer'],
            'vars' => ['array'],
        ]);

        /** @var \Gametech\Admin\Models\Employee|null $employee */
        $employee = Auth::guard('admin')->user();

        if (! $employee) {
            return response()->json([
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        // ===== 1) หา template =====
        /** @var FacebookTemplate|null $template */
        $template = FacebookTemplate::query()
            ->where('id', $data['template_id'])
            ->where(function ($q) {
                $q->where('enabled', 1)->orWhereNull('enabled');
            })
            ->first();

        if (! $template) {
            return response()->json([
                'message' => 'ไม่พบข้อความด่วนที่เลือก',
            ], 404);
        }

        // ===== 2) เตรียมตัวแปรพื้นฐานไว้แทน placeholder =====
        // โหลด relation ที่ต้องใช้ให้ครบ
        $conversation->loadMissing([
            'contact.member',
            'contact.member.bank',
        ]);

        $contact = $conversation->contact;
        $member = $contact?->member;
        $bank = $member?->bank;

        $displayName =
            $contact->display_name
            ?? $member->name
            ?? $contact->name
            ?? $contact->facebook_name
            ?? 'ลูกค้า';

        $username =
            $member->username
            ?? $contact->member_username
            ?? '';

        $memberId =
            $member->id
            ?? $contact->member_id
            ?? '';

        $phone =
            $member->mobile
            ?? $member->tel
            ?? $contact->member_mobile
            ?? '';

        $bankName =
            ($bank->bankname ?? null)
            ?? ($bank->name ?? null)
            ?? $member->bank_name
            ?? $contact->member_bank_name
            ?? '';

        $bankCode =
            $member->bank_code
            ?? $contact->member_bank_code
            ?? '';

        $accountNo =
            $member->acc_no
            ?? $member->account_no
            ?? $contact->member_acc_no
            ?? '';

        $baseVars = [
            'display_name' => $displayName,
            'username' => $username,
            'member_id' => $memberId,
            'phone' => $phone,
            'bank_name' => $bankName,
            'game_user' => $member->game_user,
            'bank_code' => $bankCode,
            'account_no' => $accountNo,
            'login_url' => UrlHelper::loginUrl(),
            'site_name' => config('app.name', config('app.domain_url')),
            'support_name' => trim(($employee->name ?? '').' '.($employee->surname ?? '')),
        ];

        // frontend สามารถ override ได้ด้วย vars ที่ส่งมา
        $vars = array_merge($baseVars, $data['vars'] ?? []);

        // ===== 3) แปลง template.message -> โครงสร้าง {version, messages[]} =====
        $structured = $this->normalizeTemplateMessage($template->message);

        $items = $structured['messages'] ?? [];
        if (! is_array($items) || ! count($items)) {
            return response()->json([
                'message' => 'template นี้ไม่มีข้อความที่ส่งได้',
            ], 422);
        }

        // ===== 4) render placeholders + แปลงเป็น LINE messages (text / image) =====
        $facebookMessages = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $kind = $item['kind'] ?? 'text';

            if ($kind === 'text') {
                $text = (string) ($item['text'] ?? '');
                $text = $this->applyTemplatePlaceholders($text, $vars);

                if ($text === '') {
                    continue;
                }

                $facebookMessages[] = [
                    'type' => 'text',
                    'text' => $text,
                ];
            } elseif ($kind === 'image') {
                $original = $item['original'] ?? $item['url'] ?? '';
                $preview = $item['preview'] ?? $original;

                $original = $this->applyTemplatePlaceholders((string) $original, $vars);
                $preview = $this->applyTemplatePlaceholders((string) $preview, $vars);

                if ($original === '') {
                    continue;
                }

                $facebookMessages[] = [
                    'type' => 'image',
                    'originalContentUrl' => $original,
                    'previewImageUrl' => $preview,
                ];
            }
            // ถ้าอนาคตมี kind อื่นค่อยเพิ่มตรงนี้
        }

        if (! count($facebookMessages)) {
            return response()->json([
                'message' => 'template นี้ไม่มีข้อความที่ส่งได้ หลังแทนตัวแปรแล้ว',
            ], 422);
        }

        // ===== 5) เลือกข้อความ text ตัวแรกไว้เป็น preview ในระบบแชต =====
        $previewText = null;
        foreach ($facebookMessages as $lm) {
            if ($lm['type'] === 'text' && ! empty($lm['text'])) {
                $previewText = $lm['text'];
                break;
            }
        }

        if (! $previewText) {
            $firstType = $facebookMessages[0]['type'] ?? 'message';
            $previewText = '['.$firstType.']';
        }

        $now = now();

        /** @var FacebookMessage $message */
        $message = FacebookMessage::create([
            'facebook_conversation_id' => $conversation->id,
            'facebook_account_id' => $conversation->facebook_account_id,
            'facebook_contact_id' => $conversation->facebook_contact_id,
            'direction' => 'outbound',
            'source' => 'quick_reply',
            'type' => 'text', // ใช้ text เป็น bubble ในหลังบ้าน
            'facebook_message_id' => null,
            'text' => $previewText,
            'payload' => [
                'template_id' => $template->id,
                'facebook_messages' => $facebookMessages,
                'vars' => $vars,
            ],
            'meta' => [
                'template_key' => $template->key ?? null,
                'template_title' => $template->title ?? $template->description ?? null,
                'sender_employee_name' => $employee->name ?? null,
            ],
            'sender_employee_id' => $employee->id ?? null,
            'sender_bot_key' => null,
            'sent_at' => $now,
        ]);

        // ===== 6) อัปเดตสรุปใน conversation ให้ตรง field จริงที่มีอยู่ =====
        $conversation->last_message_preview = Str::limit($previewText, 30);
        $conversation->last_message_at = $now;
        $conversation->unread_count = 0;
        $conversation->save();

        // ===== 7) ส่งไปที่ LINE จริง ๆ =====
        $account = $conversation->account;
        $contact = $conversation->contact;

        if ($account && $contact && $contact->facebook_user_id) {
            $result = $this->facebookMessaging->pushMessages(
                $account,
                $contact->facebook_user_id,
                $facebookMessages
            );

            if (! ($result['success'] ?? false)) {
                Log::channel('facebook_oa')->warning('[FacebookOA] ส่ง quick reply ไป LINE ไม่สำเร็จ', [
                    'conversation_id' => $conversation->id,
                    'contact_id' => $contact->id ?? null,
                    'template_id' => $template->id,
                    'status' => $result['status'] ?? null,
                    'error' => $result['error'] ?? null,
                ]);
            }
        } else {
            Log::channel('facebook_oa')->warning('[FacebookOA] ไม่สามารถส่ง quick reply ไป LINE ได้ (ไม่พบ account/contact/facebook_user_id)', [
                'conversation_id' => $conversation->id,
                'template_id' => $template->id,
            ]);
        }

        return response()->json([
            'data' => $message,
        ]);
    }

    public function replyTemplate(Request $request, FacebookConversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer'],
            'vars' => ['array'],
        ]);

        /** @var \Gametech\Admin\Models\Employee|null $employee */
        $employee = Auth::guard('admin')->user();

        if (! $employee) {
            return response()->json([
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        $employeeId = $employee->code ?? null;
        $employeeName = $employee->user_name ?? ($employee->name ?? 'พนักงาน');
        if (! $employeeId) {
            return response()->json([
                'message' => 'ไม่พบรหัสพนักงาน (code)',
            ], 403);
        }

        // กันส่ง template ในห้องที่ปิดแล้ว
        if ($conversation->status === 'closed') {
            return response()->json([
                'message' => 'เคสนี้ถูกปิดแล้ว ไม่สามารถส่งข้อความได้',
            ], 409);
        }

        //        // เคารพ lock เหมือน reply()/replyImage()
        //        if ($conversation->locked_by_employee_id &&
        //            (int) $conversation->locked_by_employee_id !== (int) $employeeId) {
        //
        //            return response()->json([
        //                'message' => 'ห้องนี้ถูกล็อกโดย '.($conversation->locked_by_employee_name ?: 'พนักงานคนอื่น').' คุณไม่สามารถตอบได้',
        //            ], 403);
        //        }

        // ===== 1) หา template =====
        /** @var FacebookTemplate|null $template */
        $template = FacebookTemplate::query()
            ->where('id', $data['template_id'])
            ->where(function ($q) {
                $q->where('enabled', 1)->orWhereNull('enabled');
            })
            ->first();

        if (! $template) {
            return response()->json([
                'message' => 'ไม่พบข้อความด่วนที่เลือก',
            ], 404);
        }

        // ===== 2) เตรียมตัวแปรพื้นฐานไว้แทน placeholder =====
        // โหลด relation ที่ต้องใช้ให้ครบ
        $conversation->loadMissing([
            'contact.member',
            'contact.member.bank',
        ]);

        $contact = $conversation->contact;
        $member = $contact?->member;
        $bank = $member?->bank;

        $displayName =
            $contact->display_name
            ?? $member->name
            ?? $contact->name
            ?? $contact->facebook_name
            ?? 'ลูกค้า';

        $username =
            $member->user_name
            ?? $contact->member_username
            ?? '';

        $memberId =
            $member->code
            ?? $contact->member_id
            ?? '';

        $phone =
            $member->mobile
            ?? $member->tel
            ?? $contact->member_mobile
            ?? '';

        $bankName =
            ($bank->name_th ?? null)
            ?? ($bank->name ?? null)
            ?? $member->bank_name
            ?? $contact->member_bank_name
            ?? '';

        $bankCode =
            $member->bank_code
            ?? $contact->member_bank_code
            ?? '';

        $accountNo =
            $member->acc_no
            ?? $member->account_no
            ?? $contact->member_acc_no
            ?? '';

        $baseVars = [
            'display_name' => $displayName,
            'username' => $username,
            'member_id' => $memberId,
            'phone' => $phone,
            'bank_name' => $bankName,
            'game_user' => $member->game_user ?? '',
            'bank_code' => $bankCode,
            'account_no' => $accountNo,
            'login_url' => UrlHelper::loginUrl(),
            'site_name' => config('app.name', config('app.domain_url')),
            'support_name' => trim(($employee->name ?? '').' '.($employee->surname ?? '')),
        ];

        // frontend สามารถ override ได้ด้วย vars ที่ส่งมา
        $vars = array_merge($baseVars, $data['vars'] ?? []);

        // ===== 3) แปลง template.message -> โครงสร้าง {version, messages[]} =====
        $structured = $this->normalizeTemplateMessage($template->message);

        $items = $structured['messages'] ?? [];
        if (! is_array($items) || ! count($items)) {
            return response()->json([
                'message' => 'template นี้ไม่มีข้อความที่ส่งได้',
            ], 422);
        }

        // ===== 4) render placeholders + แปลงเป็น LINE messages (text / image) =====
        $facebookMessages = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $kind = $item['kind'] ?? 'text';

            if ($kind === 'text') {
                $text = (string) ($item['text'] ?? '');
                $text = $this->applyTemplatePlaceholders($text, $vars);

                if ($text === '') {
                    continue;
                }

                $facebookMessages[] = [
                    'type' => 'text',
                    'text' => $text,
                ];
            } elseif ($kind === 'image') {
                $original = $item['original'] ?? $item['url'] ?? '';
                $preview = $item['preview'] ?? $original;

                $original = $this->applyTemplatePlaceholders((string) $original, $vars);
                $preview = $this->applyTemplatePlaceholders((string) $preview, $vars);

                if ($original === '') {
                    continue;
                }

                $facebookMessages[] = [
                    'type' => 'image',
                    'originalContentUrl' => $original,
                    'previewImageUrl' => $preview,
                ];
            }
            // ถ้าอนาคตมี kind อื่นค่อยเพิ่มตรงนี้
        }

        if (! count($facebookMessages)) {
            return response()->json([
                'message' => 'template นี้ไม่มีข้อความที่ส่งได้ หลังแทนตัวแปรแล้ว',
            ], 422);
        }

        // ===== 5) เลือกข้อความ text ตัวแรกไว้เป็น preview ในระบบแชต =====
        $previewText = null;
        foreach ($facebookMessages as $lm) {
            if ($lm['type'] === 'text' && ! empty($lm['text'])) {
                $previewText = $lm['text'];
                break;
            }
        }

        if (! $previewText) {
            $firstType = $facebookMessages[0]['type'] ?? 'message';
            $previewText = '['.$firstType.']';
        }

        // ===== 6) ให้ ChatService สร้าง FacebookMessage + update conversation =====
        $message = $this->chat->createOutboundQuickReplyFromAgent(
            $conversation,
            $previewText,
            (int) $employeeId,
            [
                'template_id' => $template->id,
                'facebook_messages' => $facebookMessages,
                'vars' => $vars,
            ],
            [
                'template_key' => $template->key ?? null,
                'template_title' => $template->title ?? $template->description ?? null,
                'sender_employee_name' => $employeeName,
            ]
        );

        // ===== 7) ส่งไปที่ LINE จริง ๆ =====
        $account = $conversation->account;
        $contact = $conversation->contact;

        if ($account && $contact && $contact->facebook_user_id) {
            $result = $this->facebookMessaging->pushMessages(
                $account,
                $contact->facebook_user_id,
                $facebookMessages
            );

            if (! ($result['success'] ?? false)) {
                Log::channel('facebook_oa')->warning('[FacebookOA] ส่ง quick reply ไป LINE ไม่สำเร็จ', [
                    'conversation_id' => $conversation->id,
                    'contact_id' => $contact->id ?? null,
                    'template_id' => $template->id,
                    'status' => $result['status'] ?? null,
                    'error' => $result['error'] ?? null,
                ]);
            }
        } else {
            Log::channel('facebook_oa')->warning('[FacebookOA] ไม่สามารถส่ง quick reply ไป LINE ได้ (ไม่พบ account/contact/facebook_user_id)', [
                'conversation_id' => $conversation->id,
                'template_id' => $template->id,
            ]);
        }

        return response()->json([
            'data' => $message,
        ]);
    }

    /**
     * แปลง field message จาก FacebookTemplate ให้กลายเป็นโครงสร้างมาตรฐาน
     * return: ['version' => 1, 'messages' => [...]]
     *
     * รองรับ:
     * - message เป็น array ที่มี key messages อยู่แล้ว
     * - message เป็น array simple (ถือว่าเป็น messages[] ตรง ๆ)
     * - message เป็น string JSON
     * - message เป็น string ธรรมดา
     */
    protected function normalizeTemplateMessage($raw): array
    {
        // ถ้า cast แล้วเป็น array อยู่แล้ว
        if (is_array($raw)) {
            if (isset($raw['messages']) && is_array($raw['messages'])) {
                return [
                    'version' => $raw['version'] ?? 1,
                    'messages' => $raw['messages'],
                ];
            }

            // กรณี dev เก็บเป็น array เปล่า ๆ ถือว่าเป็น messages[]
            if ($raw) {
                return [
                    'version' => $raw['version'] ?? 1,
                    'messages' => $raw,
                ];
            }

            return [
                'version' => 1,
                'messages' => [],
            ];
        }

        // ถ้าเป็น string ลอง decode JSON
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                if (isset($decoded['messages']) && is_array($decoded['messages'])) {
                    return [
                        'version' => $decoded['version'] ?? 1,
                        'messages' => $decoded['messages'],
                    ];
                }

                // ถ้า JSON แต่ไม่มี wrapper messages → ให้ถือว่าเป็น messages[]
                if ($decoded) {
                    return [
                        'version' => $decoded['version'] ?? 1,
                        'messages' => $decoded,
                    ];
                }
            }

            // ไม่ใช่ JSON → ถือว่าเป็น text ธรรมดา
            return [
                'version' => 1,
                'messages' => [
                    [
                        'kind' => 'text',
                        'text' => $raw,
                    ],
                ],
            ];
        }

        // ว่างเปล่า → ไม่มีข้อความ
        return [
            'version' => 1,
            'messages' => [],
        ];
    }

    /**
     * แทนที่ placeholder รูปแบบ {key} ด้วยค่าจาก $vars
     * เช่น {display_name}, {username}, {phone}, {bank_name}, ฯลฯ
     */
    protected function applyTemplatePlaceholders(string $text, array $vars): string
    {
        if ($text === '') {
            return $text;
        }

        return preg_replace_callback('/\{(\w+)\}/u', function ($m) use ($vars) {
            $key = $m[1];

            if (array_key_exists($key, $vars)) {
                return (string) $vars[$key];
            }

            // ถ้าไม่รู้จักตัวแปรนี้ จะเลือก:
            // - คง {key} ไว้ (เพื่อ debug ง่าย)
            // - หรือจะ return '' ก็ได้ ถ้าอยากให้เงียบ ๆ
            return $m[0];
        }, $text);
    }

    /**
     * ดึงรายการ Quick Reply สำหรับห้องแชตนี้
     *
     * Route:
     *   GET /admin/facebook-oa/conversations/{conversation}/quick-replies
     *   (ฝั่ง JS เรียกผ่าน this.apiUrl('conversations/{id}/quick-replies'))
     */
    public function quickReplies(Request $request, FacebookConversation $conversation): JsonResponse
    {
        // ถ้าต้องการ filter ตาม OA สามารถใช้ $conversation->facebook_account_id ได้ในอนาคต
        // ตอนนี้เอาแบบ global quick_reply ทั้งระบบก่อน
        $query = FacebookTemplate::query()
            ->where('category', 'quick_reply')
            ->where('enabled', true)
            ->orderBy('id', 'asc');

        $templates = $query->get();

        $conversation->loadMissing([
            'contact.member',
            'contact.member.bank',
        ]);

        /** @var \Gametech\Admin\Models\Employee|null $employee */
        $employee = Auth::guard('admin')->user();

        // ====== เตรียม vars สำหรับแทนตัวแปรใน preview ======
        $contact = $conversation->contact;
        $member = $contact?->member;
        $bank = $member?->bank;

        $displayName =
            $contact->display_name
            ?? $member?->name
            ?? $contact->name
            ?? $contact->facebook_name
            ?? 'ลูกค้า';

        $username =
            $member?->user_name
            ?? $contact->member_username
            ?? '';

        $memberId =
            $member?->code
            ?? $contact->member_id
            ?? '';

        $phone =
            $member?->mobile
            ?? $member?->tel
            ?? $contact->member_mobile
            ?? '';

        $bankName =
            ($bank->name_th ?? null)
            ?? ($bank->name ?? null)
            ?? $member?->bank_name
            ?? $contact->member_bank_name
            ?? '';

        $bankCode =
            $member?->bank_code
            ?? $contact->member_bank_code
            ?? '';

        $accountNo =
            $member?->acc_no
            ?? $member?->account_no
            ?? $contact->member_acc_no
            ?? '';

        $supportName = $employee
            ? trim(($employee->name ?? '').' '.($employee->surname ?? ''))
            : '';

        $baseVars = [
            'display_name' => $displayName,
            'username' => $username,
            'member_id' => $memberId,
            'phone' => $phone,
            'bank_name' => $bankName,
            'game_user' => $member?->game_user ?? '',
            'bank_code' => $bankCode,
            'account_no' => $accountNo,
            'login_url' => UrlHelper::loginUrl(),
            'site_name' => config('app.name', config('app.domain_url')),
            'support_name' => $supportName,
        ];

        $items = $templates->map(function (FacebookTemplate $t) use ($baseVars) {
            $label = $t->title
                ?? $t->description
                ?? $t->key
                ?? ('Template #'.$t->id);

            $rawMessage = $t->message ?? null;
            $body = '';

            // พยายามดึง "ข้อความหลัก" ออกมาเป็น text preview
            if (is_array($rawMessage)) {
                // สมมติใช้โครงสร้าง { version, messages: [ { kind, text, ... } ] }
                if (isset($rawMessage['messages']) && is_array($rawMessage['messages'])) {
                    foreach ($rawMessage['messages'] as $m) {
                        if (($m['kind'] ?? null) === 'text' && ! empty($m['text'])) {
                            $body = (string) $m['text'];
                            break;
                        }
                    }

                    // ถ้ายังไม่ได้อะไร ลองหยิบข้อความแรกที่มี text
                    if ($body === '' && count($rawMessage['messages'])) {
                        $first = $rawMessage['messages'][0];
                        if (! empty($first['text'])) {
                            $body = (string) $first['text'];
                        }
                    }
                }
            } elseif (is_string($rawMessage) && $rawMessage !== '') {
                // ลองดูว่าเป็น JSON หรือเปล่า
                $decoded = json_decode($rawMessage, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    if (isset($decoded['messages']) && is_array($decoded['messages'])) {
                        foreach ($decoded['messages'] as $m) {
                            if (($m['kind'] ?? null) === 'text' && ! empty($m['text'])) {
                                $body = (string) $m['text'];
                                break;
                            }
                        }
                        if ($body === '' && count($decoded['messages'])) {
                            $first = $decoded['messages'][0];
                            if (! empty($first['text'])) {
                                $body = (string) $first['text'];
                            }
                        }
                    }
                } else {
                    // เป็น text ธรรมดา
                    $body = $rawMessage;
                }
            }

            $body = (string) $body;

            // แทน placeholder ด้วยข้อมูลลูกค้าจริง
            if ($body !== '') {
                $body = $this->applyTemplatePlaceholders($body, $baseVars);
            }

            // ตัดให้สั้นสำหรับ preview
            $preview = $body !== ''
                ? Str::limit(preg_replace('/\s+/u', ' ', $body), 80)
                : '';

            return [
                'id' => $t->id,
                'key' => $t->key ?? null,
                'label' => $label,
                'category' => $t->category,
                'preview' => $preview,
                'body_preview' => $body,
            ];
        });

        return response()->json([
            'data' => $items,
        ]);
    }

    /**
     * ดึง content รูปของ message สำหรับ frontend (proxy / lazy download)
     *
     * Route (แนะนำ):
     *   GET /admin/facebook-oa/messages/{message}/content
     */
    public function content(FacebookMessage $message)
    {
        if ($message->type !== 'image') {
            abort(404);
        }

        try {
            $payloadMsg = data_get($message->payload, 'message', []);
            $path = $payloadMsg['path'] ?? null;
            $url = $payloadMsg['contentUrl'] ?? ($payloadMsg['previewUrl'] ?? null);

            // 1) ถ้ามี path และไฟล์อยู่ใน disk → stream
            if ($path && Storage::disk('public')->exists($path)) {
                $mime = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';
                $content = Storage::disk('public')->get($path);

                return response($content, 200)->header('Content-Type', $mime);
            }

            // 2) ถ้า payload มี URL แบบ https อยู่แล้ว → redirect ไปเลย
            if ($url && preg_match('#^https?://#i', $url)) {
                return redirect($url);
            }

            // 3) ถ้า contentProvider.type = facebook → ลองโหลดจาก LINE ตอนนี้
            $contentProviderType = data_get($message->payload, 'message.contentProvider.type');
            if ($contentProviderType === 'facebook' && $message->facebook_message_id) {
                $conversation = $message->conversation()->with('account')->first();
                $account = $conversation?->account;

                if ($account) {
                    $res = $this->facebookMessaging->downloadMessageContent($account, $message->facebook_message_id, 'image');

                    if ($res && ! empty($res['path'])) {
                        // update payload
                        $payloadMsg['contentUrl'] = $res['url'];
                        $payloadMsg['previewUrl'] = $res['url'];
                        $payloadMsg['path'] = $res['path'];

                        $payload = $message->payload ?? [];
                        $payload['message'] = $payloadMsg;
                        $message->payload = $payload;
                        $message->save();

                        // stream ไฟล์ที่เพิ่งเซฟ
                        $path = $res['path'];
                        if (Storage::disk('public')->exists($path)) {
                            $mime = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';
                            $content = Storage::disk('public')->get($path);

                            return response($content, 200)->header('Content-Type', $mime);
                        }

                        return redirect($res['url']);
                    }

                    // ถ้าโหลดไม่ได้ (404 จาก LINE) → log แล้ว 404
                    Log::channel('facebook_oa')->warning('[FacebookChat] ดึง content รูปจาก LINE ไม่สำเร็จ', [
                        'message_id' => $message->id,
                        'facebook_message_id' => $message->facebook_message_id,
                    ]);
                }
            }

            // 4) สุดท้ายถ้าไม่มีอะไรเลย → 404
            Log::channel('facebook_oa')->warning('[FacebookChat] ไม่พบ content รูปสำหรับ message', [
                'message_id' => $message->id,
                'facebook_message_id' => $message->facebook_message_id,
            ]);

            abort(404);
        } catch (\Throwable $e) {
            Log::channel('facebook_oa')->error('[FacebookChat] exception ใน content()', [
                'message_id' => $message->id,
                'facebook_message_id' => $message->facebook_message_id,
                'error' => $e->getMessage(),
            ]);

            abort(500);
        }
    }

    public function findMember(Request $request): JsonResponse
    {
        $memberId = trim((string) $request->get('member_id', ''));

        if ($memberId === '') {
            return response()->json([
                'message' => 'member_id ห้ามว่าง',
            ], 422);
        }

        try {
            // หมายเหตุ:
            // - ตรงนี้ปรับให้ตรงระบบจริงของโบ๊ทได้เลย
            // - ตัวอย่าง: ใช้ repository กลางของ Member
            /** @var \Prettus\Repository\Contracts\RepositoryInterface $memberRepo */
            $memberRepo = app('Gametech\Member\Repositories\MemberRepository');

            $member = $memberRepo->findWhere([
                'user_name' => $memberId,
            ])->first();

            if (! $member) {
                // กันเคสอยากหาจาก id ด้วย
                $member = $memberRepo->findWhere([
                    'tel' => $memberId,
                ])->first();
            }

            if (! $member) {
                return response()->json([
                    'message' => 'ไม่พบสมาชิกตาม Member ID ที่ระบุ',
                ], 404);
            }

            // ตัดให้เหลือ field ที่ front ใช้จริง
            $data = [
                'id' => $member->id ?? $member->code ?? $memberId,
                'name' => $member->name ?? ($member->full_name ?? null),
                'username' => $member->username ?? ($member->user_name ?? null),
                'mobile' => $member->mobile ?? ($member->tel ?? null),
            ];

            return response()->json([
                'message' => 'success',
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            Log::channel('facebook_oa')->error('[FacebookOA] findMember error', [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'ค้นหาสมาชิกไม่สำเร็จ กรุณาลองใหม่',
            ], 500);
        }
    }

    public function loadBank(Request $request): JsonResponse
    {

        try {
            // หมายเหตุ:
            // - ตรงนี้ปรับให้ตรงระบบจริงของโบ๊ทได้เลย
            // - ตัวอย่าง: ใช้ repository กลางของ Member
            /** @var \Prettus\Repository\Contracts\RepositoryInterface $bankRepo */
            $bankRepo = app('Gametech\Payment\Repositories\BankRepository');

            $default = [
                'value' => '',
                'text' => '== เลือกธนาคาร ==',
            ];

            $banks = $bankRepo->findWhere([
                'enable' => 'Y',
                'show_regis' => 'Y',
            ])->sortBy('name_th')
                ->map(fn ($item) => [
                    'value' => $item->code,
                    'text' => $item->name_th,
                ])->values()->prepend($default);

            return response()->json([
                'message' => 'success',
                'bank' => $banks,
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'กรุณาลองใหม่',
            ], 500);
        }
    }

    public function checkBank(Request $request): JsonResponse
    {
        $result = [
            'success' => false,
            'firstname' => null,
            'lastname' => null,
        ];

        $bankCode = $request->input('bank_code');
        $account_no = $request->input('account_no');

        try {
            /** @var \Gametech\FacebookOA\Services\RegisterFlowService $flow */
            $flow = app(RegisterFlowService::class);

            // normalize ให้เป็นมาตรฐานเดียวกับ flow สมัครหลัก
            $normalizedAccount = $flow->normalizeAccountNo($account_no);

            if (! $normalizedAccount) {
                return response()->json([
                    'message' => 'เลขบัญชีไม่ถูกต้อง',
                    'success' => false,
                ], 200);
            }

            // ใช้ logic เดียวกับระบบสมัครปกติ
            if ($flow->isBankAccountAlreadyUsed($bankCode, $normalizedAccount)) {
                return response()->json([
                    'success' => false,
                    'message' => 'เลขบัญชี มีในระบบแล้ว ไม่มาสารถใช้ได้',
                ]);
            }

            $apiBankCode = $this->mapBankCodeForExternalApi($bankCode);
            if (! $apiBankCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'ระบบไม่รองรับ ธนาคารดังกล่าว',
                ]);
            }

            try {
                $postData = [
                    'toBankAccNumber' => $normalizedAccount,
                    'toBankAccNameCode' => $apiBankCode,
                ];

                $response = Http::withHeaders([
                    'x-api-key' => 'af96aa1c-e1f5-4c22-ab96-7f5453704aa9',
                ])->asJson()->post('https://me2me.biz/getname.php', $postData);
            } catch (\Throwable $e) {
                // connect error / timeout → ปล่อยให้ไปถามชื่อเอง
                return response()->json([
                    'success' => false,
                    'message' => 'กรุณาลองใหม่อีกครั้ง',
                ]);
            }

            if (! $response->successful()) {
                // status code != 200 → ปล่อยให้ไปถามชื่อเอง
                return response()->json([
                    'success' => false,
                    'message' => 'กรุณาลองใหม่อีกครั้ง',
                ]);
            }

            $json = $response->json();

            $status = (bool) data_get($json, 'status');
            $msg = (string) (data_get($json, 'msg', '') ?? '');

            if (! $status) {
                // เคส status=false แยกตามเงื่อนไขที่ต้องการ
                if (Str::contains($msg, 'ข้อมูลเลขบัญชีปลายทางไม่ถูกต้อง')) {
                    // ให้ถามเลขบัญชีใหม่
                    $result['message'] = $msg;
                } elseif (Str::contains($msg, 'ไม่รองรับ')) {
                    // เช่น "toBankAccNameCode : LHBT ไม่รองรับ" → ไป step ถัดไป
                    $result['message'] = $msg;
                }

                return response()->json($result);
            }

            // ดึงชื่อ-นามสกุลจาก API และ normalize
            $rawFullname = (string) data_get($json, 'data.accountName', '');
            $cleanFullname = $flow->cleanInvisibleAndSpaces($rawFullname);

            if ($cleanFullname === '') {
                return response()->json($result);
            }

            $fullname = $flow->splitNameUniversal($cleanFullname);

            $firstname = $fullname['firstname'] ?? '';
            $lastname = $fullname['lastname'] ?? '';

            if ($firstname === '' || $lastname === '') {
                return response()->json($result);
            }

            $result['success'] = true;
            $result['firstname'] = $firstname;
            $result['lastname'] = $lastname;

            return response()->json($result);

        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'กรุณาลองใหม่',
            ], 500);
        }
    }

    protected function mapBankCodeForExternalApi(string $bankcode): ?string
    {
        switch ((string) $bankcode) {
            case '1':
                return 'BBL';
            case '2':
                return 'KBANK';
            case '3':
                return 'KTB';
            case '4':
                return 'SCB';
            case '5':
                return 'GHB';
            case '6':
                return 'KKP';
            case '7':
                return 'CIMB';
            case '19':
            case '15':
            case '10':
                return 'TTB';
            case '11':
                return 'BAY';
            case '12':
                return 'UOB';
            case '13':
                return 'LHB';
            case '14':
                return 'GSB';
            case '17':
                return 'BAAC';
            default:
                return null;
        }
    }

    public function checkPhone(Request $request): JsonResponse
    {
        $phone = $request->input('phone');

        try {
            /** @var \Gametech\FacebookOA\Services\RegisterFlowService $flow */
            $flow = app(RegisterFlowService::class);

            // normalize ให้เป็นมาตรฐานเดียวกับ flow สมัครหลัก
            $normalizedPhone = $flow->normalizePhone($phone);

            if (! $normalizedPhone) {
                return response()->json([
                    'message' => 'เบอร์โทรไม่ถูกต้อง',
                    'bank' => false,
                ], 200);
            }

            // ใช้ logic เดียวกับระบบสมัครปกติ
            $exists = $flow->isPhoneAlreadyUsed($normalizedPhone);

            return response()->json([
                'message' => 'success',
                'bank' => $exists,    // เหมือนของเดิม: bank = true ถ้าซ้ำ
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'กรุณาลองใหม่',
            ], 500);
        }
    }

    public function registerMember(Request $request): JsonResponse
    {
        try {
            /** @var \Gametech\FacebookOA\Services\RegisterFlowService $flow */
            $flow = app(RegisterFlowService::class);

            // 1) รับค่าจาก popup
            $phone = $request->input('phone');
            $bankCode = trim((string) $request->input('bank_code'));
            $accountNo = trim((string) $request->input('account_no'));
            $name = trim((string) $request->input('name'));
            $surname = trim((string) $request->input('surname'));

            // 2) Normalize เบอร์ก่อน
            $normalizedPhone = $flow->normalizePhone($phone);

            if (! $normalizedPhone) {
                return response()->json([
                    'success' => false,
                    'message' => 'เบอร์โทรไม่ถูกต้อง',
                ], 200);
            }

            // 3) เช็คเบอร์ซ้ำด้วย logic เดิมของระบบ
            if ($flow->isPhoneAlreadyUsed($normalizedPhone)) {
                return response()->json([
                    'success' => false,
                    'message' => 'เบอร์นี้มีอยู่ในระบบแล้ว',
                ], 200);
            }

            // 4) ตรวจสอบข้อมูลให้ครบ
            if (! $bankCode || ! $accountNo || ! $name || ! $surname) {
                return response()->json([
                    'success' => false,
                    'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน',
                ], 200);
            }

            // 5) normalize เลขบัญชีให้เหมือน flow สมัครหลัก
            $normalizedAccount = $flow->normalizeAccountNo($accountNo);

            if (! $normalizedAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'เลขบัญชีไม่ถูกต้อง',
                ], 200);
            }

            // 6) เคส TW = account_no = phone
            $isTw = (strtoupper($bankCode) === 'TW' || (string) $bankCode === '18');
            if ($isTw) {
                if ($normalizedAccount !== $normalizedPhone) {
                    return response()->json([
                        'success' => false,
                        'message' => 'สำหรับธนาคาร TW เลขบัญชีต้องเป็นเบอร์โทรเท่านั้น',
                    ], 200);
                }
            }

            // 7) เช็คซ้ำเลขบัญชีด้วย logic เดียวกับ flow สมัครบอท
            if ($flow->isBankAccountAlreadyUsed($bankCode, $normalizedAccount)) {
                return response()->json([
                    'success' => false,
                    'message' => 'เลขบัญชี มีในระบบแล้ว ไม่สามารถใช้ได้',
                ], 200);
            }

            // 8) สมัครสมาชิกจริงผ่าน Service กลางของระบบ
            $payload = [
                'phone' => $normalizedPhone,
                'bank_code' => $bankCode,
                'account_no' => $normalizedAccount,
                'name' => $name,
                'surname' => $surname,
                'created_from' => 'facebook_staff', // ระบุว่ามาจาก Support Staff
            ];

            $result = $flow->registerFromStaff($payload);

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'สมัครสมาชิกไม่สำเร็จ',
                ], 200);
            }

            // สำเร็จ
            return response()->json([
                'success' => true,
                'message' => 'สมัครสมาชิกสำเร็จ',
                'member' => $result['member'] ?? null,
            ], 200);

        } catch (\Throwable $e) {

            // เก็บ log
            Log::channel('facebook_oa')->error('[FacebookOA] registerMember error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่',
            ], 500);
        }
    }

    public function attachMember(Request $request, FacebookContact $contact): JsonResponse
    {
        $memberId = trim((string) $request->input('member_id', ''));
        $display_name = trim((string) $request->input('display_name', ''));

        if ($memberId === '') {
            return response()->json([
                'message' => 'member_id ห้ามว่าง',
            ], 422);
        }
        if ($display_name === '') {
            return response()->json([
                'message' => 'Display Name ห้ามว่าง',
            ], 422);
        }

        // ดึงข้อมูล member มาใส่เพิ่ม (optional)
        $memberName = null;
        $memberUsername = null;
        $memberMobile = null;
        $memberBankName = null;
        $memberAccNo = null;
        $memberDisplay = $display_name;

        try {
            /** @var \Prettus\Repository\Contracts\RepositoryInterface $memberRepo */
            $memberRepo = app('Gametech\Member\Repositories\MemberRepository');

            $member = $memberRepo->findWhere([
                'code' => $memberId,
            ])->first();

            if (! $member) {
                $member = $memberRepo->find($memberId);
            }

            if ($member) {
                $memberName = $member->name ?? null;
                $memberUsername = $member->user_name ?? null;
                $memberMobile = $member->tel ?? null;
                $memberBankName = $member->bank?->name_th ?? null;
                $memberAccNo = $member->acc_no ?? null;
            }
        } catch (\Throwable $e) {
            // ถ้าดึง member พัง ไม่เป็นไร แค่ log ไว้ แล้วผูกเฉพาะ member_id
            Log::channel('facebook_oa')->warning('[FacebookOA] attachMember: cannot load member detail', [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);
        }

        // เตรียม payload สำหรับอัปเดตทุก FacebookContact ที่มี facebook_user_id เดียวกัน
        $update = [
            'member_id' => $memberId,
            'display_name' => $memberDisplay,
        ];

        if ($memberUsername !== null) {
            $update['member_username'] = $memberUsername;
        }

        if ($memberMobile !== null) {
            $update['member_mobile'] = $memberMobile;
        }

        // ถ้าอยากเก็บชื่อ/ธนาคาร/เลขบัญชีลง contact ด้วย เปิดส่วนนี้ได้
        // if ($memberName !== null) {
        //     $update['member_name'] = $memberName;
        // }
        // if ($memberBankName !== null) {
        //     $update['member_bank_name'] = $memberBankName;
        // }
        // if ($memberAccNo !== null) {
        //     $update['member_acc_no'] = $memberAccNo;
        // }

        // อัปเดตทุก contact ที่มี facebook_user_id เดียวกัน
        FacebookContact::where('facebook_user_id', $contact->facebook_user_id)->update($update);

        // reload contact ปัจจุบันให้ใช้ค่าล่าสุด
        $contact->refresh();

        return response()->json([
            'message' => 'success',
            'data' => [
                'id' => $contact->id,
                'display_name' => $contact->display_name,
                'member_id' => $contact->member_id,
                'member_username' => $contact->member_username,
                'member_mobile' => $contact->member_mobile,
                'member_name' => $memberName,
                'member_bank_name' => $memberBankName,
                'member_acc_no' => $memberAccNo,
                'picture_url' => $contact->picture_url,
            ],
        ]);
    }

    public function accept(Request $request, FacebookConversation $conversation): JsonResponse
    {
        $employee = Auth::guard('admin')->user();
        $employeeId = $employee?->code ?? null;
        $employeeName = $employee->user_name ?? ($employee->name ?? 'พนักงาน');

        if (! $employeeId) {
            return response()->json([
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        // ห้ามรับเรื่องถ้าปิดเคสแล้ว
        if ($conversation->status === 'closed') {
            return response()->json([
                'message' => 'ห้องนี้ถูกปิดเคสแล้ว',
            ], 409);
        }

        // ถ้ามีคนรับเรื่องไว้แล้ว และไม่ใช่เราเอง
        if ($conversation->assigned_employee_id &&
            (int) $conversation->assigned_employee_id !== (int) $employeeId) {

            return response()->json([
                'message' => 'ห้องนี้ถูกพนักงานคนอื่นรับเรื่องแล้ว',
            ], 409);
        }

        // เซต owner (assigned)
        $conversation->assigned_employee_id = (int) $employeeId;
        $conversation->assigned_employee_name = $employeeName;
        $conversation->assigned_at = now();

        // สถานะห้อง
        if ($conversation->status !== 'closed') {
            $conversation->status = 'assigned';
        }

        // optional: lock ห้องให้ตัวเองด้วย (ใช้ locked_by_employee_id)
        $conversation->locked_by_employee_id = (int) $employeeId;
        $conversation->locked_by_employee_name = $employeeName;
        $conversation->locked_at = now();

        $conversation->save();

        $conversationFresh = $conversation->fresh([
            'contact.member',
            'account',
            'registerSessions' => function ($q) {
                $q->where('status', 'in_progress');
            },
        ]) ?? $conversation;

        DB::afterCommit(function () use ($conversationFresh) {
            event(new FacebookOAChatConversationUpdated($conversationFresh));
            event(new FacebookOAConversationAssigned($conversationFresh));
        });

        return response()->json([
            'message' => 'success',
            'data' => $conversationFresh,
        ]);
    }

    /**
     * ล็อกห้อง (บอกว่าตอนนี้ใครกำลังใช้งานห้องนี้)
     *
     * Route:
     *   POST /admin/facebook-oa/conversations/{conversation}/lock
     */
    public function lock(Request $request, FacebookConversation $conversation): JsonResponse
    {
        $employee = Auth::guard('admin')->user();
        $employeeId = $employee?->code ?? null;
        $employeeName = $employee->user_name ?? ($employee->name ?? 'พนักงาน');

        if (! $employeeId) {
            return response()->json([
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        // ถ้ามีคนอื่นล็อกอยู่ และไม่ใช่เราเอง
        if ($conversation->locked_by_employee_id &&
            (int) $conversation->locked_by_employee_id !== (int) $employeeId) {

            return response()->json([
                'message' => 'ห้องนี้กำลังใช้งานโดย '.($conversation->locked_by_employee_name ?: 'พนักงานคนอื่น'),
            ], 409);
        }

        $conversation->locked_by_employee_id = (int) $employeeId;
        $conversation->locked_by_employee_name = $employeeName;
        $conversation->locked_at = now();
        $conversation->save();

        $conversationFresh = $conversation->fresh([
            'contact.member',
            'account',
            'registerSessions' => function ($q) {
                $q->where('status', 'in_progress');
            },
        ]) ?? $conversation;

        DB::afterCommit(function () use ($conversationFresh) {
            event(new FacebookOAChatConversationUpdated($conversationFresh));
            event(new FacebookOAConversationLocked($conversationFresh));
        });

        return response()->json([
            'message' => 'success',
            'data' => $conversationFresh,
        ]);
    }

    /**
     * ปลดล็อกห้อง
     *
     * Route:
     *   POST /admin/facebook-oa/conversations/{conversation}/unlock
     */
    public function unlock(Request $request, FacebookConversation $conversation): JsonResponse
    {
        $employee = Auth::guard('admin')->user();
        $employeeId = $employee?->code ?? null;

        if (! $employeeId) {
            return response()->json([
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        // ป้องกันไม่ให้คนอื่นมาปลดล็อกห้องที่เราใช้งานอยู่
        if ($conversation->locked_by_employee_id &&
            (int) $conversation->locked_by_employee_id !== (int) $employeeId) {

            return response()->json([
                'message' => 'ห้องนี้ถูกล็อกโดยพนักงานคนอื่น',
            ], 403);
        }

        $conversation->locked_by_employee_id = null;
        $conversation->locked_by_employee_name = null;
        $conversation->locked_at = null;
        $conversation->save();

        $conversationFresh = $conversation->fresh([
            'contact.member',
            'account',
            'registerSessions' => function ($q) {
                $q->where('status', 'in_progress');
            },
        ]) ?? $conversation;

        DB::afterCommit(function () use ($conversationFresh) {
            event(new FacebookOAChatConversationUpdated($conversationFresh));
            event(new FacebookOAConversationLocked($conversationFresh)); // ใช้ event เดิม แต่ payload lock เป็น null
        });

        return response()->json([
            'message' => 'success',
            'data' => $conversationFresh,
        ]);
    }

    public function close(Request $request, FacebookConversation $conversation): JsonResponse
    {
        $employee = Auth::guard('admin')->user();
        $employeeId = $employee?->code ?? null;
        $employeeName = $employee->user_name ?? ($employee->name ?? 'พนักงาน');

        if (! $employeeId) {
            return response()->json([
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        // ถ้าปิดอยู่แล้ว ไม่ต้องทำอะไร
        if ($conversation->status === 'closed') {
            $conversationFresh = $conversation->fresh([
                'contact.member',
                'account',
                'registerSessions' => function ($q) {
                    $q->where('status', 'in_progress');
                },
            ]) ?? $conversation;

            DB::afterCommit(function () use ($conversationFresh) {
                event(new FacebookOAChatConversationUpdated($conversationFresh));
                event(new FacebookOAConversationClosed($conversationFresh));
            });

            return response()->json([
                'message' => 'success',
                'data' => $conversationFresh,
            ]);
        }

        // เซตสถานะเป็น closed
        $conversation->status = 'closed';
        $conversation->closed_by_employee_id = $employeeId;
        $conversation->closed_by_employee_name = $employeeName;
        $conversation->closed_at = now();

        // ปลดล็อกห้องด้วย (กันกรณีค้างล็อก)
        $conversation->locked_by_employee_id = null;
        $conversation->locked_by_employee_name = null;
        $conversation->locked_at = null;

        $conversation->save();

        $conversationFresh = $conversation->fresh([
            'contact.member',
            'account',
            'registerSessions' => function ($q) {
                $q->where('status', 'in_progress');
            },
        ]) ?? $conversation;

        DB::afterCommit(function () use ($conversationFresh) {
            event(new FacebookOAChatConversationUpdated($conversationFresh));
            event(new FacebookOAConversationClosed($conversationFresh));
        });

        return response()->json([
            'message' => 'success',
            'data' => $conversationFresh,
        ]);
    }

    public function open(Request $request, FacebookConversation $conversation): JsonResponse
    {
        $employee = Auth::guard('admin')->user();
        $employeeId = $employee?->code ?? null;
        $employeeName = $employee->user_name ?? ($employee->name ?? 'พนักงาน');

        if (! $employeeId) {
            return response()->json([
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        // ===== ป้องกันลูกค้าเดียวกันมี open ซ้อนหลายห้อง =====
        $contactId = $conversation->facebook_contact_id;
        $accountId = $conversation->facebook_account_id;

        $existingOpen = FacebookConversation::query()
            ->where('facebook_contact_id', $contactId)
            ->where('facebook_account_id', $accountId)
            ->whereIn('status', ['open', 'assigned'])
            ->where('id', '!=', $conversation->id)
            ->first();

        if ($existingOpen) {
            $existingOpen->load([
                'contact.member',
                'account',
                'registerSessions' => function ($q) {
                    $q->where('status', 'in_progress');
                },
            ]);

            // ไม่ต้องถือว่า error ให้ frontend พาไปห้องนี้แทน
            return response()->json([
                'message' => 'มีห้องที่เปิดอยู่สำหรับลูกค้าคนนี้แล้ว ระบบจะพาไปยังห้องนั้น',
                'data' => $existingOpen,
            ]);
        }
        // ===============================================

        // ถ้าเปิดอยู่แล้ว ไม่ต้องทำอะไร
        if ($conversation->status !== 'closed') {
            $conversationFresh = $conversation->fresh([
                'contact.member',
                'account',
                'registerSessions' => function ($q) {
                    $q->where('status', 'in_progress');
                },
            ]) ?? $conversation;

            DB::afterCommit(function () use ($conversationFresh) {
                event(new FacebookOAChatConversationUpdated($conversationFresh));
                event(new FacebookOAConversationOpen($conversationFresh));
            });

            return response()->json([
                'message' => 'success',
                'data' => $conversationFresh,
            ]);
        }

        // เซตสถานะเป็น assigned (เปิดใหม่และถือว่าเราเป็นคนดูแล)
        $conversation->status = 'assigned';
        $conversation->closed_by_employee_id = null;
        $conversation->closed_by_employee_name = null;
        $conversation->closed_at = null;

        // ล็อกห้องด้วย
        $conversation->locked_by_employee_id = $employeeId;
        $conversation->locked_by_employee_name = $employeeName;
        $conversation->locked_at = now();

        $conversation->save();

        $conversationFresh = $conversation->fresh([
            'contact.member',
            'account',
            'registerSessions' => function ($q) {
                $q->where('status', 'in_progress');
            },
        ]) ?? $conversation;

        DB::afterCommit(function () use ($conversationFresh) {
            event(new FacebookOAChatConversationUpdated($conversationFresh));
            event(new FacebookOAConversationOpen($conversationFresh));
        });

        return response()->json([
            'message' => 'success',
            'data' => $conversationFresh,
        ]);
    }

    public function cancelRegister(FacebookConversation $conversation)
    {
        // หา session ค้าง
        $session = FacebookRegisterSession::where('facebook_conversation_id', $conversation->id)
            ->where('status', 'in_progress')
            ->orderByDesc('id')
            ->first();

        if (! $session) {
            return response()->json([
                'message' => 'ไม่มี flow สมัครที่กำลังทำงาน',
            ], 404);
        }

        // ยกเลิก session
        $session->status = 'cancelled';
        $session->current_step = RegisterFlowService::STEP_FINISHED;
        $session->save();

        // broadcast อัปเดตสถานะ
        DB::afterCommit(function () use ($conversation) {
            $conversation->load([
                'contact.member',
                'account',
                'registerSessions' => fn ($q) => $q->where('status', 'in_progress'),
            ]);

            event(new FacebookOAChatConversationUpdated($conversation));
        });

        return response()->json([
            'message' => 'success',
        ]);
    }

    public function getBalance(
        Request $request,
        MemberRepository $memberRepository,
        GameUserRepository $gameUserRepository
    ): JsonResponse {
        $conversationId = (int) $request->input('conversation_id');

        if (! $conversationId) {
            return response()->json([
                'ok' => false,
                'message' => 'ไม่พบค่า conversation_id',
            ], 422);
        }

        /** @var FacebookConversation|null $conversation */
        $conversation = FacebookConversation::query()
            ->with('contact')
            ->find($conversationId);

        if (! $conversation) {
            return response()->json([
                'ok' => false,
                'message' => 'ไม่พบห้องสนทนา',
            ], 404);
        }

        // ดึง member จาก contact
        $memberId = $conversation->contact?->member_id;
        $memberUsername = $conversation->contact?->member_username;

        if (! $memberId) {
            return response()->json([
                'ok' => false,
                'message' => 'ห้องนี้ยังไม่ได้ผูกกับสมาชิกในระบบ',
            ], 422);
        }

        $member = $memberRepository->find($memberId);
        $gameUser = $member->gameUser;

        if (! $member) {
            return response()->json([
                'ok' => false,
                'message' => 'ไม่พบข้อมูลสมาชิก (อาจถูกลบออกจากระบบแล้ว)',
            ], 404);
        }

        $balance = 0.0;
        $rawResponse = null;

        try {
            $game = core()->getGame();

            // NOTE: ปรับ parameter ให้ตรงกับ signature จริงของ checkBalance
            // บางระบบใช้ game_code + user_name, บางที่ใช้ game_id + game_user
            $rawResponse = $gameUserRepository->checkBalance(
                $game->id,
                $member->game_user // ถ้าจริง ๆ เป็น user_name ก็แก้เป็น $member->user_name
            );

            // กันเคส provider ตอบอะไรแปลก ๆ กลับมา
            $success = is_array($rawResponse) ? (bool) ($rawResponse['success'] ?? false) : false;

            if ($success) {
                $score = $rawResponse['score'] ?? 0;
                $balance = (float) $score;
            } else {
                // ดึง message จาก provider ถ้ามี
                $providerMessage = is_array($rawResponse)
                    ? ($rawResponse['message'] ?? 'ไม่สามารถดึงยอดเงินจากผู้ให้บริการได้')
                    : 'ไม่สามารถดึงยอดเงินจากผู้ให้บริการได้';

                return response()->json([
                    'ok' => false,
                    'message' => $providerMessage,
                ], 502);
            }
        } catch (Throwable $e) {
            // log ไว้เผื่อ debug
            Log::channel('facebook_oa')->warning('[FacebookOA] getBalance error', [
                'conversation_id' => $conversationId,
                'member_id' => $memberId,
                'response' => $rawResponse,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'เกิดข้อผิดพลาดระหว่างดึงยอดเงิน',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'success',
            'data' => [
                'member_id' => $memberId,
                'member_username' => $memberUsername,
                'member_gameuser' => $member->game_user,
                'member_turnover' => $gameUser->amount_balance,
                'member_limit' => $gameUser->withdraw_limit_amount,
                'member_pro' => ($gameUser->pro_code > 0 || $gameUser->amount_balance > 0) ? true : false,
                'member_pro_name' => $gameUser->promotion?->name_th ?? '',
                'balance' => $balance,
                'balance_text' => number_format($balance, 2),
                'currency' => 'THB',
            ],
        ]);
    }

    public function getBalanceMulti(
        Request $request,
        MemberRepository $memberRepository,
        GameUserRepository $gameUserRepository
    ): JsonResponse {
        $conversationId = (int) $request->input('conversation_id');

        if (! $conversationId) {
            return response()->json([
                'ok' => false,
                'message' => 'ไม่พบค่า conversation_id',
            ], 422);
        }

        /** @var FacebookConversation|null $conversation */
        $conversation = FacebookConversation::query()
            ->with('contact')
            ->find($conversationId);

        if (! $conversation) {
            return response()->json([
                'ok' => false,
                'message' => 'ไม่พบห้องสนทนา',
            ], 404);
        }

        // ดึง member จาก contact
        $memberId = $conversation->contact?->member_id;
        $memberUsername = $conversation->contact?->member_username;

        if (! $memberId) {
            return response()->json([
                'ok' => false,
                'message' => 'ห้องนี้ยังไม่ได้ผูกกับสมาชิกในระบบ',
            ], 422);
        }

        $member = $memberRepository->find($memberId);
        //        $gameUser = $member->gameUser;

        if (! $member) {
            return response()->json([
                'ok' => false,
                'message' => 'ไม่พบข้อมูลสมาชิก (อาจถูกลบออกจากระบบแล้ว)',
            ], 404);
        }

        $balance = 0.0;
        $rawResponse = null;

        try {
            //            $game = core()->getGame();

            // NOTE: ปรับ parameter ให้ตรงกับ signature จริงของ checkBalance
            // บางระบบใช้ game_code + user_name, บางที่ใช้ game_id + game_user
            //            $rawResponse = $gameUserRepository->checkBalance(
            //                $game->id,
            //                $member->game_user // ถ้าจริง ๆ เป็น user_name ก็แก้เป็น $member->user_name
            //            );

            // กันเคส provider ตอบอะไรแปลก ๆ กลับมา
            $score = $member->balance ?? 0;
            $balance = (float) $score;

        } catch (Throwable $e) {
            // log ไว้เผื่อ debug
            Log::channel('facebook_oa')->warning('[FacebookOA] getBalance error', [
                'conversation_id' => $conversationId,
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'เกิดข้อผิดพลาดระหว่างดึงยอดเงิน',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'success',
            'data' => [
                'member_id' => $memberId,
                'member_username' => $memberUsername,
                'member_gameuser' => '',
                'member_turnover' => 0,
                'member_limit' => 0,
                'member_pro' => false,
                'member_pro_name' => '',
                'balance' => $balance,
                'balance_text' => number_format($balance, 2),
                'currency' => 'THB',
            ],
        ]);
    }
}
