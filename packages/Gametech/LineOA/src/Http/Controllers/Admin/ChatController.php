<?php

namespace Gametech\LineOA\Http\Controllers\Admin;

use Gametech\Admin\Http\Controllers\AppBaseController;
use Gametech\Admin\Models\Admin;
use Gametech\Game\Repositories\GameUserRepository;
use Gametech\LineOA\Events\LineOAChatConversationUpdated;
use Gametech\LineOA\Events\LineOAConversationAssigned;
use Gametech\LineOA\Events\LineOAConversationClosed;
use Gametech\LineOA\Events\LineOAConversationLocked;
use Gametech\LineOA\Events\LineOAConversationOpen;
use Gametech\LineOA\Models\LineContact;
use Gametech\LineOA\Models\LineConversation;
use Gametech\LineOA\Models\LineConversationNote;
use Gametech\LineOA\Models\LineMessage;
use Gametech\LineOA\Models\LineRegisterSession;
use Gametech\LineOA\Models\LineTemplate;
use Gametech\LineOA\Services\ChatService;
use Gametech\LineOA\Services\LineMessagingClient;
use Gametech\LineOA\Services\RegisterFlowService;
use Gametech\LineOA\Support\UrlHelper;
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

    protected LineMessagingClient $lineMessaging;

    public function __construct(ChatService $chat, LineMessagingClient $lineMessaging)
    {
        $this->chat = $chat;
        $this->lineMessaging = $lineMessaging;
    }

    /**
     * แสดงหน้าแชต (Blade + Vue UI)
     */
    public function page()
    {
        return view('admin::module.line-oa.index');
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

        $query = LineConversation::query()
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
            $query->where('line_account_id', $accountId);
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
        } elseif ($status === 'assigned') {
            // เคสที่ปิดแล้วเท่านั้น
            $query->where('status', 'assigned');

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
            'data' => $paginator->getCollection()->map(function (LineConversation $conv) {
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
                    'is_pinned' => (bool) $conv->is_pinned,

                    'line_account' => [
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
    public function show(Request $request, LineConversation $conversation): JsonResponse
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
            /** @var \Gametech\LineOA\Models\LineConversation|null $prevConv */
            $prevConv = LineConversation::query()->find($previousId);

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
                    event(new LineOAChatConversationUpdated($conv));
                });
            }
        }

        $messagesQuery = LineMessage::query()
            ->where('line_conversation_id', $conversation->id)
            ->orderByDesc('id');

        if ($beforeId) {
            $messagesQuery->where('id', '<', $beforeId);
        }

        $messages = $messagesQuery
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $markAsReadToken = null;
        foreach ($messages as $msg) {
            if ($msg->direction === 'inbound' && $msg->source === 'user') {
                $meta = $msg->meta ?? [];
                if (! empty($meta['mark_as_read_token'])) {
                    // เก็บตัวสุดท้าย (ล่าสุด) ทับไปเรื่อย ๆ
                    $markAsReadToken = $meta['mark_as_read_token'];
                }
            }
        }

        // clear unread
        if ($conversation->unread_count > 0) {

            // 1) ถ้ามี token + account → ยิงไปที่ LINE
            if ($markAsReadToken && $conversation->account) {
                $result = $this->lineMessaging->markMessagesAsRead($conversation->account, $markAsReadToken);

                if (! $result['success']) {
                    Log::warning('[LineChat] markMessagesAsRead ไม่สำเร็จ', [
                        'conversation_id' => $conversation->id,
                        'account_id' => $conversation->account?->id,
                        'error' => $result['error'] ?? null,
                        'status' => $result['status'] ?? null,
                    ]);
                }
            }

            // 2) ไม่ว่าจะ mark สำเร็จหรือไม่ → ถือว่าฝั่งระบบ “ถูกเปิดอ่านแล้ว”
            $conversation->unread_count = 0;
            $conversation->save();

            DB::afterCommit(function () use ($conversation) {
                $conv = $conversation->fresh([
                    'contact.member',
                    'account',
                    'registerSessions' => function ($q) {
                        $q->where('status', 'in_progress');
                    },
                ]) ?? $conversation;

                event(new LineOAChatConversationUpdated($conv));
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

                'is_pinned' => (bool) $conversation->is_pinned,

                'incoming_language' => $conversation->incoming_language,
                'outgoing_language' => $conversation->outgoing_language,

                'line_account' => [
                    'id' => $conversation->account?->id,
                    'name' => $conversation->account?->name,
                ],
                'contact' => [
                    'id' => $conversation->contact?->id,
                    'display_name' => $conversation->contact?->display_name,
                    'line_user_id' => $conversation->contact?->line_user_id,
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
            'messages' => $messages->map(function (LineMessage $m) {
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
                    'is_pinned' => (bool) $m->is_pinned,
                ];
            }),
        ];

        return response()->json($data);
    }

    /**
     * ส่ง TEXT จาก admin
     */
    public function reply(Request $request, LineConversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string'],
            'reply_to_message_id' => ['nullable', 'integer'],
        ]);

        $text = trim($data['text']);

        if ($text === '') {
            return response()->json([
                'message' => 'ข้อความห้ามว่าง',
            ], 422);
        }

        /** @var \Gametech\Admin\Models\Admin|null $employee */
        $employee = Auth::guard('admin')->user();
        $employeeId = $employee?->code ?? null;

        if (! $employeeId) {
            return response()->json([
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        // ถ้าห้องเคยถูกปิดไว้ แล้วทีมงานตอบใหม่ → เปิดสถานะกลับเป็น open
        if ($conversation->status === 'closed') {
            $conversation->status = 'open';
            $conversation->closed_by_employee_id = null;
            $conversation->closed_by_employee_name = null;
            $conversation->closed_at = null;
            $conversation->save();
        }

        // meta พื้นฐาน
        $meta = [
            'employee_name' => $employee->user_name ?? null,
        ];

        // -------------------------
        // ใส่ข้อมูล reply_to ลงใน meta (ถ้ามี)
        // -------------------------
        $replyToId = $data['reply_to_message_id'] ?? null;

        if ($replyToId) {
            /** @var \Gametech\LineOA\Models\LineMessage|null $replyTo */
            $replyTo = LineMessage::query()
                ->where('line_conversation_id', $conversation->id)
                ->where('id', $replyToId)
                ->first();

            if ($replyTo) {
                $meta['reply_to'] = [
                    'id' => $replyTo->id,
                    'text' => mb_strimwidth((string) $replyTo->text, 0, 80, '...'),
                    'direction' => $replyTo->direction,
                    'source' => $replyTo->source,
                    'type' => $replyTo->type,
                    'sent_at' => optional($replyTo->sent_at)->toIso8601String(),
                ];
            }
        }

        // --------- สร้าง message outbound ฝั่งระบบเรา ---------
        /** @var LineMessage $message */
        $message = $this->chat->createOutboundMessageFromAgent(
            $conversation,
            $text,
            $employeeId,
            $meta
        );

        $conversation->loadMissing(['account', 'contact.member']);
        $account = $conversation->account;
        $contact = $conversation->contact;

        // -------------------------
        // เลือกข้อความที่จะใช้เป็น "เนื้อ" การตอบลูกค้า (หลังแปลแล้ว ถ้ามี)
        // -------------------------
        $lineText = $text;

        $msgMeta = $message->meta;
        if (is_array($msgMeta)) {
            $outboundTrans = $msgMeta['translation_outbound'] ?? null;

            // ถ้ามีข้อความแปล → ใช้ตัวแปลเป็นเนื้อหลักในการคุยกับลูกค้า
            if (is_array($outboundTrans) && ! empty($outboundTrans['translated_text'])) {
                $lineText = $outboundTrans['translated_text'];
            }
        }

        // -------------------------
        // สร้าง payload ที่จะส่งไป LINE
        // - ถ้ามี reply_to และเป็น text → ใช้ Flex message (แบบ B)
        // - ถ้าไม่มี reply_to → pushText() ปกติ
        // -------------------------
        if ($account && $contact && $contact->line_user_id) {
            $lineUserId = $contact->line_user_id;

            $replyMeta = is_array($msgMeta) ? ($msgMeta['reply_to'] ?? null) : null;
            $hasReply = is_array($replyMeta) && ! empty($replyMeta['text']);

            if ($hasReply && (($replyMeta['type'] ?? 'text') === 'text')) {

                $quoted = (string) $replyMeta['text'];
                $altText = "ตอบกลับ: {$quoted}\n".$lineText;
                $altText = mb_strimwidth($altText, 0, 390, '...', 'UTF-8');

                // -------------------------
                // เตรียมข้อมูล header: avatar + display name
                // -------------------------
                $contactRelation = $conversation->contact;
                $member = $contactRelation?->member;

                // ตรวจว่า message ต้นทางเป็นของลูกค้าจริงไหม
                $isCustomerMessage =
                    (($replyMeta['direction'] ?? null) === 'inbound') &&
                    (($replyMeta['source'] ?? null) === 'user');

                if ($isCustomerMessage) {
                    // เคสตอบกลับข้อความลูกค้า → ใช้ชื่อ + รูปลูกค้า
                    $headerName =
                        $contactRelation->display_name
                        ?? $member->name
                        ?? $contactRelation->name
                        ?? $contactRelation->line_name
                        ?? 'ลูกค้า';

                    $headerAvatarUrl = $contactRelation->picture_url ?? null;
                } else {
                    // เคสตอบกลับข้อความของพนักงานเอง หรือ outbound อื่น ๆ
                    $headerName = 'พนักงาน';
                    $headerAvatarUrl = null; // ไม่ต้องมีรูป
                }

                // -------------------------
                // สร้าง contents ของ header
                // -------------------------
                $headerContents = [];

                if (! empty($headerAvatarUrl)) {
                    $headerContents[] = [
                        'type' => 'image',
                        'url' => $headerAvatarUrl,
                        'size' => 'xs',
                        'aspectRatio' => '1:1',
                        'aspectMode' => 'cover',
                        'gravity' => 'center',
                        'flex' => 0,
                        'margin' => 'sm',
                        'align' => 'start',
                    ];
                }

                $headerContents[] = [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'spacing' => 'xs',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => $headerName,
                            'weight' => 'bold',
                            'size' => 'sm',
                            'wrap' => true,
                        ],
                        [
                            'type' => 'text',
                            'text' => 'ตอบกลับข้อความก่อนหน้า',
                            'size' => 'xs',
                            'color' => '#888888',
                        ],
                    ],
                ];

                // -------------------------
                // ประกอบ Flex bubble
                // -------------------------
                $flex = [
                    'type' => 'flex',
                    'altText' => $altText,
                    'contents' => [
                        'type' => 'bubble',
                        'body' => [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'spacing' => 'sm',
                            'contents' => [
                                // แถวบน: avatar + ชื่อ + label
                                [
                                    'type' => 'box',
                                    'layout' => 'horizontal',
                                    'spacing' => 'md',
                                    'alignItems' => 'center',
                                    'contents' => $headerContents,
                                ],

                                // กล่องเทา: ข้อความเดิม (quoted)
                                [
                                    'type' => 'box',
                                    'layout' => 'vertical',
                                    'backgroundColor' => '#F5F5F5',
                                    'cornerRadius' => 'md',
                                    'paddingAll' => '8px',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => $quoted,
                                            'size' => 'sm',
                                            'wrap' => true,
                                            'color' => '#555555',
                                        ],
                                    ],
                                ],

                                // ข้อความตอบกลับของเรา (หรือข้อความแปล)
                                [
                                    'type' => 'text',
                                    'text' => $lineText,
                                    'wrap' => true,
                                    'size' => 'md',
                                ],
                            ],
                        ],
                    ],
                ];

                $result = $this->lineMessaging->pushMessages(
                    $account,
                    $lineUserId,
                    [$flex]
                );

                if (($result['success'] ?? false)) {
                    // ===== ดึง sentMessages.id และ quoteToken มาเก็บลง message =====
                    $body = $result['body'] ?? null;
                    if (is_array($body)) {
                        $sent = $body['sentMessages'] ?? null;
                        if (is_array($sent) && ! empty($sent[0])) {
                            $first = $sent[0];
                            $lineMessageId = $first['id'] ?? null;
                            $quoteToken = $first['quoteToken'] ?? null;

                            $metaForMsg = $message->meta;
                            if (! is_array($metaForMsg)) {
                                $metaForMsg = $metaForMsg ? (array) $metaForMsg : [];
                            }

                            if ($quoteToken) {
                                $metaForMsg['quote_token'] = $quoteToken;
                            }

                            // จะเก็บ sentMessages ทั้งชุดไว้ใน meta เผื่อ debug ทีหลังด้วย
                            $metaForMsg['sent_messages'] = $sent;

                            if ($lineMessageId) {
                                $message->line_message_id = $lineMessageId;
                            }

                            $message->meta = $metaForMsg;
                            $message->save();
                        }
                    }
                } else {
                    Log::channel('line_oa')->warning('[LineChat] ส่ง Flex reply ไป LINE ไม่สำเร็จ', [
                        'conversation_id' => $conversation->id,
                        'contact_id' => $contact->id,
                        'status' => $result['status'] ?? null,
                        'error' => $result['error'] ?? null,
                    ]);
                }
            } else {
                // ไม่มี reply_to หรือไม่ใช่ text → ส่งเป็นข้อความปกติ
                $result = $this->lineMessaging->pushText(
                    $account,
                    $lineUserId,
                    $lineText
                );

                if (($result['success'] ?? false)) {
                    // ===== ดึง sentMessages.id และ quoteToken มาเก็บลง message =====
                    $body = $result['body'] ?? null;
                    if (is_array($body)) {
                        $sent = $body['sentMessages'] ?? null;
                        if (is_array($sent) && ! empty($sent[0])) {
                            $first = $sent[0];
                            $lineMessageId = $first['id'] ?? null;
                            $quoteToken = $first['quoteToken'] ?? null;

                            $metaForMsg = $message->meta;
                            if (! is_array($metaForMsg)) {
                                $metaForMsg = $metaForMsg ? (array) $metaForMsg : [];
                            }

                            if ($quoteToken) {
                                $metaForMsg['quote_token'] = $quoteToken;
                            }
                            $metaForMsg['sent_messages'] = $sent;

                            if ($lineMessageId) {
                                $message->line_message_id = $lineMessageId;
                            }

                            $message->meta = $metaForMsg;
                            $message->save();
                        }
                    }
                } else {
                    Log::channel('line_oa')->warning('[LineChat] ส่งข้อความไป LINE ไม่สำเร็จ', [
                        'conversation_id' => $conversation->id,
                        'contact_id' => $contact->id,
                        'error' => $result['error'] ?? null,
                        'status' => $result['status'] ?? null,
                    ]);
                }
            }
        } else {
            Log::channel('line_oa')->warning('[LineChat] ไม่สามารถส่งข้อความไป LINE ได้ (ไม่พบ account/contact/line_user_id)', [
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
                'text' => $message->text, // ในระบบเก็บเฉพาะข้อความที่ agent พิมพ์
                'sent_at' => optional($message->sent_at)->toIso8601String(),
                'sender_employee_id' => $message->sender_employee_id,
                'sender_bot_key' => $message->sender_bot_key,
                'meta' => $message->meta,      // มี reply_to / quote_token / sent_messages ให้หลังบ้านใช้
                'payload' => $message->payload,
                'is_pinned' => (bool) $message->is_pinned,
            ],
        ]);
    }

    /**
     * ส่ง IMAGE จาก admin
     */
    public function replyImage(Request $request, LineConversation $conversation): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'], // 5MB
        ]);

        $file = $request->file('image');

        $employee = Auth::guard('admin')->user();
        $employeeId = $employee?->code ?? null;

        if (! $employeeId) {
            return response()->json([
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        // ถ้าห้องเคยถูกปิดไว้ แล้วทีมงานส่งรูปใหม่ → เปิดสถานะกลับเป็น open
        if ($conversation->status === 'closed') {
            $conversation->status = 'open';
            $conversation->closed_by_employee_id = null;
            $conversation->closed_by_employee_name = null;
            $conversation->closed_at = null;
            $conversation->save();
        }

        /** @var LineMessage $message */
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

        if ($account && $contact && $contact->line_user_id && $originalUrl) {
            $result = $this->lineMessaging->sendImageMessage(
                $account,
                $contact->line_user_id,
                $originalUrl,
                $previewUrl
            );

            if (($result['success'] ?? false)) {
                // ===== ดึง sentMessages.id และ quoteToken มาเก็บลง message =====
                $body = $result['body'] ?? null;
                if (is_array($body)) {
                    $sent = $body['sentMessages'] ?? null;
                    if (is_array($sent) && ! empty($sent[0])) {
                        $first = $sent[0];
                        $lineMessageId = $first['id'] ?? null;
                        $quoteToken = $first['quoteToken'] ?? null;

                        $metaForMsg = $message->meta;
                        if (! is_array($metaForMsg)) {
                            $metaForMsg = $metaForMsg ? (array) $metaForMsg : [];
                        }

                        if ($quoteToken) {
                            $metaForMsg['quote_token'] = $quoteToken;
                        }
                        $metaForMsg['sent_messages'] = $sent;

                        if ($lineMessageId) {
                            $message->line_message_id = $lineMessageId;
                        }

                        $message->meta = $metaForMsg;
                        $message->save();
                    }
                }
            } else {
                Log::channel('line_oa')->warning('[LineChat] ส่งรูปไป LINE ไม่สำเร็จ', [
                    'conversation_id' => $conversation->id,
                    'contact_id' => $contact->id,
                    'image_url' => $originalUrl,
                    'error' => $result['error'] ?? null,
                    'status' => $result['status'] ?? null,
                ]);
            }
        } else {
            Log::channel('line_oa')->warning('[LineChat] ไม่สามารถส่งรูปไป LINE ได้ (ไม่พบ account/contact/line_user_id หรือ url ว่าง)', [
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
                'is_pinned' => (bool) $message->is_pinned,
            ],
        ]);
    }

    public function replySticker(Request $request, LineConversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'package_id' => ['required', 'string'],
            'sticker_id' => ['required', 'string'],
        ]);

        $packageId = trim($data['package_id']);
        $stickerId = trim($data['sticker_id']);

        if ($packageId === '' || $stickerId === '') {
            return response()->json([
                'message' => 'ต้องระบุ package_id และ sticker_id',
            ], 422);
        }

        $employee = Auth::guard('admin')->user();
        $employeeId = $employee?->code ?? null;

        if (! $employeeId) {
            return response()->json([
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        // 1) บันทึกข้อความ outbound sticker ลง DB
        $message = $this->chat->createOutboundStickerFromAgent(
            $conversation,
            $packageId,
            $stickerId,
            $employeeId,
            [
                'employee_code' => $employee->code ?? null,
                'employee_name' => $employee->name ?? null,
            ]
        );

        // 2) ยิงสติกเกอร์ไปที่ LINE จริง ๆ
        $account = $conversation->account;
        $contact = $conversation->contact;

        if ($account && $contact && $contact->line_user_id) {
            // ใช้ helper ใหม่
            $result = $this->lineMessaging->pushSticker(
                $account,
                $contact->line_user_id,
                $packageId,
                $stickerId
            );

            if (! $result['success']) {
                Log::warning('[LineChat] ส่งสติกเกอร์ไป LINE ไม่สำเร็จ', [
                    'conversation_id' => $conversation->id,
                    'contact_id' => $contact->id,
                    'error' => $result['error'] ?? null,
                    'status' => $result['status'] ?? null,
                ]);
            }
        } else {
            Log::warning('[LineChat] ไม่สามารถส่งสติกเกอร์ไป LINE ได้ (ไม่พบ account/contact/line_user_id)', [
                'conversation_id' => $conversation->id,
            ]);
        }

        return response()->json([
            'message' => 'success',
            'data' => [
                'id' => $message->id,
                'direction' => $message->direction,
                'source' => $message->source,
                'type' => $message->type, // 'sticker'
                'text' => $message->text,
                'sent_at' => optional($message->sent_at)->toDateTimeString(),
                'sender_employee_id' => $message->sender_employee_id,
                'meta' => $message->meta,
                'is_pined' => (bool) $message->is_pined,
            ],
        ]);
    }

    /**
     * ส่งข้อความจาก LINE template (รองรับ JSON หลายข้อความ เช่น text + image)
     *
     * POST /admin/line-oa/conversations/{conversation}/reply-template
     */
    public function replyTemplate(Request $request, LineConversation $conversation): JsonResponse
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

        $employeeId = $employee->code ?? null;
        $employeeName = $employee->user_name ?? ($employee->name ?? 'พนักงาน');
        if (! $employeeId) {
            return response()->json([
                'message' => 'ไม่พบรหัสพนักงาน (code)',
            ], 403);
        }

        // ถ้าห้องเคยถูกปิดไว้ แล้วทีมงานส่ง template ใหม่ → เปิดสถานะกลับเป็น open
        if ($conversation->status === 'closed') {
            $conversation->status = 'open';
            $conversation->closed_by_employee_id = null;
            $conversation->closed_by_employee_name = null;
            $conversation->closed_at = null;
            $conversation->save();
        }

        // กันส่ง template ในห้องที่ปิดแล้ว
        //        if ($conversation->status === 'closed') {
        //            return response()->json([
        //                'message' => 'เคสนี้ถูกปิดแล้ว ไม่สามารถส่งข้อความได้',
        //            ], 409);
        //        }

        //        // เคารพ lock เหมือน reply()/replyImage()
        //        if ($conversation->locked_by_employee_id &&
        //            (int) $conversation->locked_by_employee_id !== (int) $employeeId) {
        //
        //            return response()->json([
        //                'message' => 'ห้องนี้ถูกล็อกโดย '.($conversation->locked_by_employee_name ?: 'พนักงานคนอื่น').' คุณไม่สามารถตอบได้',
        //            ], 403);
        //        }

        // ===== 1) หา template =====
        /** @var LineTemplate|null $template */
        $template = LineTemplate::query()
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
            ?? $contact->line_name
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
        $lineMessages = [];

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

                $lineMessages[] = [
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

                $lineMessages[] = [
                    'type' => 'image',
                    'originalContentUrl' => $original,
                    'previewImageUrl' => $preview,
                ];
            }
            // ถ้าอนาคตมี kind อื่นค่อยเพิ่มตรงนี้
        }

        if (! count($lineMessages)) {
            return response()->json([
                'message' => 'template นี้ไม่มีข้อความที่ส่งได้ หลังแทนตัวแปรแล้ว',
            ], 422);
        }

        // ===== 5) เลือกข้อความ text ตัวแรกไว้เป็น preview ในระบบแชต =====
        $previewText = null;
        foreach ($lineMessages as $lm) {
            if ($lm['type'] === 'text' && ! empty($lm['text'])) {
                $previewText = $lm['text'];
                break;
            }
        }

        if (! $previewText) {
            $firstType = $lineMessages[0]['type'] ?? 'message';
            $previewText = '['.$firstType.']';
        }

        // ===== 6) ให้ ChatService สร้าง LineMessage + update conversation =====
        $message = $this->chat->createOutboundQuickReplyFromAgent(
            $conversation,
            $previewText,
            (int) $employeeId,
            [
                'template_id' => $template->id,
                'line_messages' => $lineMessages,
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

        if ($account && $contact && $contact->line_user_id) {
            $result = $this->lineMessaging->pushMessages(
                $account,
                $contact->line_user_id,
                $lineMessages
            );

            if (! ($result['success'] ?? false)) {
                Log::channel('line_oa')->warning('[LineOA] ส่ง quick reply ไป LINE ไม่สำเร็จ', [
                    'conversation_id' => $conversation->id,
                    'contact_id' => $contact->id ?? null,
                    'template_id' => $template->id,
                    'status' => $result['status'] ?? null,
                    'error' => $result['error'] ?? null,
                ]);
            }
        } else {
            Log::channel('line_oa')->warning('[LineOA] ไม่สามารถส่ง quick reply ไป LINE ได้ (ไม่พบ account/contact/line_user_id)', [
                'conversation_id' => $conversation->id,
                'template_id' => $template->id,
            ]);
        }

        return response()->json([
            'data' => $message,
        ]);
    }

    public function replyTemplateText(Request $request, LineConversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer'],
            'vars' => ['array'],
            'preview_only' => ['sometimes', 'boolean'],
        ]);

        /** @var \Gametech\Admin\Models\Admin|null $employee */
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

        // ===== 1) หา template =====
        /** @var LineTemplate|null $template */
        $template = LineTemplate::query()
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
            ?? $contact->line_name
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
        $lineMessages = [];

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

                $lineMessages[] = [
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

                $lineMessages[] = [
                    'type' => 'image',
                    'originalContentUrl' => $original,
                    'previewImageUrl' => $preview,
                ];
            }
            // future: kind อื่นค่อยเพิ่มตรงนี้
        }

        if (! count($lineMessages)) {
            return response()->json([
                'message' => 'template นี้ไม่มีข้อความที่ส่งได้ หลังแทนตัวแปรแล้ว',
            ], 422);
        }

        // ===== 5) เลือกข้อความ text ตัวแรกไว้เป็น preview =====
        $previewText = null;
        foreach ($lineMessages as $lm) {
            if (($lm['type'] ?? null) === 'text' && ! empty($lm['text'])) {
                $previewText = $lm['text'];
                break;
            }
        }

        if (! $previewText) {
            $firstType = $lineMessages[0]['type'] ?? 'message';
            $previewText = '['.$firstType.']';
        }

        // ===== 5.5) โหมด preview_only: แค่คืนข้อความให้ frontend ไปใส่ textarea =====
        if ($request->boolean('preview_only')) {
            return response()->json([
                'data' => [
                    'text' => $previewText,
                    'line_messages' => $lineMessages,
                ],
            ]);
        }

        // ===== 6) จากนี้คือโหมด "ส่งจริง" เหมือนของเดิม =====

        // ถ้าห้องเคยถูกปิดไว้ แล้วทีมงานส่ง template ใหม่ → เปิดสถานะกลับเป็น open
        if ($conversation->status === 'closed') {
            $conversation->status = 'open';
            $conversation->closed_by_employee_id = null;
            $conversation->closed_by_employee_name = null;
            $conversation->closed_at = null;
            $conversation->save();
        }

        /** @var LineMessage $message */
        $message = $this->chat->createOutboundQuickReplyFromAgent(
            $conversation,
            $previewText,
            (int) $employeeId,
            [
                'template_id' => $template->id,
                'line_messages' => $lineMessages,
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

        if ($account && $contact && $contact->line_user_id) {
            $result = $this->lineMessaging->pushMessages(
                $account,
                $contact->line_user_id,
                $lineMessages
            );

            if (($result['success'] ?? false)) {
                // ===== ดึง sentMessages.id/quoteToken ตัวแรกมาเก็บใน message preview =====
                $body = $result['body'] ?? null;
                if (is_array($body)) {
                    $sent = $body['sentMessages'] ?? null;
                    if (is_array($sent) && ! empty($sent[0])) {
                        $first = $sent[0];
                        $lineMessageId = $first['id'] ?? null;
                        $quoteToken = $first['quoteToken'] ?? null;

                        $metaForMsg = $message->meta;
                        if (! is_array($metaForMsg)) {
                            $metaForMsg = $metaForMsg ? (array) $metaForMsg : [];
                        }

                        if ($quoteToken) {
                            $metaForMsg['quote_token'] = $quoteToken;
                        }
                        // เก็บ sentMessages ทั้งชุดเผื่อ debug ด้วย
                        $metaForMsg['sent_messages'] = $sent;

                        if ($lineMessageId) {
                            $message->line_message_id = $lineMessageId;
                        }

                        $message->meta = $metaForMsg;
                        $message->save();
                    }
                }
            } else {
                Log::channel('line_oa')->warning('[LineOA] ส่ง quick reply ไป LINE ไม่สำเร็จ', [
                    'conversation_id' => $conversation->id,
                    'contact_id' => $contact->id ?? null,
                    'template_id' => $template->id,
                    'status' => $result['status'] ?? null,
                    'error' => $result['error'] ?? null,
                ]);
            }
        } else {
            Log::channel('line_oa')->warning('[LineOA] ไม่สามารถส่ง quick reply ไป LINE ได้ (ไม่พบ account/contact/line_user_id)', [
                'conversation_id' => $conversation->id,
                'template_id' => $template->id,
            ]);
        }

        return response()->json([
            'data' => $message,
        ]);
    }

    /**
     * แปลง field message จาก LineTemplate ให้กลายเป็นโครงสร้างมาตรฐาน
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
     *   GET /admin/line-oa/conversations/{conversation}/quick-replies
     *   (ฝั่ง JS เรียกผ่าน this.apiUrl('conversations/{id}/quick-replies'))
     */
    public function quickReplies(Request $request, LineConversation $conversation): JsonResponse
    {
        // ถ้าต้องการ filter ตาม OA สามารถใช้ $conversation->line_account_id ได้ในอนาคต
        // ตอนนี้เอาแบบ global quick_reply ทั้งระบบก่อน
        $query = LineTemplate::query()
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
            ?? $contact->line_name
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

        $items = $templates->map(function (LineTemplate $t) use ($baseVars) {
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
     *   GET /admin/line-oa/messages/{message}/content
     */
    public function content(LineMessage $message)
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

            // 3) ถ้า contentProvider.type = line → ลองโหลดจาก LINE ตอนนี้
            $contentProviderType = data_get($message->payload, 'message.contentProvider.type');
            if ($contentProviderType === 'line' && $message->line_message_id) {
                $conversation = $message->conversation()->with('account')->first();
                $account = $conversation?->account;

                if ($account) {
                    $res = $this->lineMessaging->downloadMessageContent($account, $message->line_message_id, 'image');

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
                    Log::channel('line_oa')->warning('[LineChat] ดึง content รูปจาก LINE ไม่สำเร็จ', [
                        'message_id' => $message->id,
                        'line_message_id' => $message->line_message_id,
                    ]);
                }
            }

            // 4) สุดท้ายถ้าไม่มีอะไรเลย → 404
            Log::channel('line_oa')->warning('[LineChat] ไม่พบ content รูปสำหรับ message', [
                'message_id' => $message->id,
                'line_message_id' => $message->line_message_id,
            ]);

            abort(404);
        } catch (\Throwable $e) {
            Log::channel('line_oa')->error('[LineChat] exception ใน content()', [
                'message_id' => $message->id,
                'line_message_id' => $message->line_message_id,
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
            Log::channel('line_oa')->error('[LineOA] findMember error', [
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
            /** @var \Gametech\LineOA\Services\RegisterFlowService $flow */
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
            /** @var \Gametech\LineOA\Services\RegisterFlowService $flow */
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

    public function checkUser(Request $request): JsonResponse
    {
        $username = $request->input('username');

        try {
            /** @var \Gametech\LineOA\Services\RegisterFlowService $flow */
            $flow = app(RegisterFlowService::class);

            // ใช้ logic เดียวกับระบบสมัครปกติ
            $exists = $flow->isUsernameAlreadyUsed($username);

            return response()->json([
                'message' => 'success',
                'duplicate' => $exists,    // true = ซ้ำ, false = ใช้ได้
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
            /** @var \Gametech\LineOA\Services\RegisterFlowService $flow */
            $flow = app(RegisterFlowService::class);

            // อ่านโหมดจาก frontend ('phone' หรือ 'username')
            $mode = $request->input('register_mode', 'phone');

            // ค่าที่ใช้ร่วมทุกโหมด
            $bankCode = trim((string) $request->input('bank_code'));
            $accountNo = trim((string) $request->input('account_no'));
            $name = trim((string) $request->input('name'));
            $surname = trim((string) $request->input('surname'));

            // ตรวจความครบถ้วนของฟิลด์หลัก
            if (! $bankCode || ! $accountNo || ! $name || ! $surname) {
                return response()->json([
                    'success' => false,
                    'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน',
                ], 200);
            }

            /*
            |--------------------------------------------------------------------------
            | MODE 1: สมัครด้วยเบอร์โทร (โทร = login id แบบเดิม)
            |--------------------------------------------------------------------------
            */
            if ($mode === 'phone') {

                $phone = $request->input('phone');
                $normalizedPhone = $flow->normalizePhone($phone);

                if (! $normalizedPhone) {
                    return response()->json([
                        'success' => false,
                        'message' => 'เบอร์โทรไม่ถูกต้อง',
                    ], 200);
                }

                if ($flow->isPhoneAlreadyUsed($normalizedPhone)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'เบอร์นี้มีอยู่ในระบบแล้ว',
                    ], 200);
                }

                // normalize เลขบัญชี
                $normalizedAccount = $flow->normalizeAccountNo($accountNo);
                if (! $normalizedAccount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'เลขบัญชีไม่ถูกต้อง',
                    ], 200);
                }

                // กรณี TW
                $isTw = (strtoupper($bankCode) === 'TW' || (string) $bankCode === '18');
                if ($isTw && $normalizedAccount !== $normalizedPhone) {
                    return response()->json([
                        'success' => false,
                        'message' => 'สำหรับธนาคาร TW เลขบัญชีต้องเป็นเบอร์โทรเท่านั้น',
                    ], 200);
                }

                // เช็คบัญชีซ้ำ
                if ($flow->isBankAccountAlreadyUsed($bankCode, $normalizedAccount)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'เลขบัญชี มีในระบบแล้ว ไม่สามารถใช้ได้',
                    ], 200);
                }

                // payload สำหรับ phone-mode
                $payload = [
                    'phone' => $normalizedPhone,
                    'bank_code' => $bankCode,
                    'account_no' => $normalizedAccount,
                    'name' => $name,
                    'surname' => $surname,
                    'created_from' => 'line_staff',
                    'register_mode' => 'phone',
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | MODE 2: สมัครด้วย Username
            |--------------------------------------------------------------------------
            */
            elseif ($mode === 'username') {

                $username = strtolower(trim((string) $request->input('username')));

                // ตรวจรูปแบบ username
                if (!preg_match('/^[a-z0-9]+$/', $username)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'รูปแบบยูสเซอร์เนมไม่ถูกต้อง (ใช้ a-z0-9 เท่านั้น)',
                    ], 200);
                }

                if ($flow->isUsernameAlreadyUsed($username)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'ยูสเซอร์เนมนี้มีอยู่แล้ว',
                    ], 200);
                }

                // normalize เลขบัญชีให้เหมือนเดิม
                $normalizedAccount = $flow->normalizeAccountNo($accountNo);
                if (! $normalizedAccount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'เลขบัญชีไม่ถูกต้อง',
                    ], 200);
                }

                // กรณี TW: username-mode ไม่ได้ใช้เบอร์อยู่แล้ว จึงไม่จำเป็นต้องบังคับเลขบัญชี == phone
                // ถ้าต้องการ enforce เงื่อนไขใหม่ให้บอกได้

                // เช็คบัญชีซ้ำ
                if ($flow->isBankAccountAlreadyUsed($bankCode, $normalizedAccount)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'เลขบัญชี มีในระบบแล้ว ไม่สามารถใช้ได้',
                    ], 200);
                }

                // Payload สำหรับ username-mode
                $payload = [
                    'username' => $username,
                    'bank_code' => $bankCode,
                    'account_no' => $normalizedAccount,
                    'name' => $name,
                    'surname' => $surname,
                    'created_from' => 'line_staff',
                    'register_mode' => 'username',
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | ถ้า mode ผิด → error
            |--------------------------------------------------------------------------
            */
            else {
                return response()->json([
                    'success' => false,
                    'message' => 'โหมดสมัครไม่ถูกต้อง',
                ], 200);
            }

            /*
            |--------------------------------------------------------------------------
            | เรียก RegisterFlowService → สมัครจริง
            |--------------------------------------------------------------------------
            */
            $result = $flow->registerFromStaff($payload);

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'สมัครสมาชิกไม่สำเร็จ',
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'สมัครสมาชิกสำเร็จ',
                'member' => $result['member'] ?? null,
            ], 200);

        } catch (\Throwable $e) {

            Log::channel('line_oa')->error('[LineOA] registerMember error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่',
            ], 500);
        }
    }

    public function attachMember(Request $request, LineContact $contact): JsonResponse
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
            Log::channel('line_oa')->warning('[LineOA] attachMember: cannot load member detail', [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);
        }

        // เตรียม payload สำหรับอัปเดตทุก LineContact ที่มี line_user_id เดียวกัน
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

        // อัปเดตทุก contact ที่มี line_user_id เดียวกัน
        LineContact::where('line_user_id', $contact->line_user_id)->update($update);

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

    public function accept(Request $request, LineConversation $conversation): JsonResponse
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
                'message' => 'แชตที่เลือก เสร็จสิ้นไปแล้ว',
            ], 409);
        }

        // ถ้ามีคนรับเรื่องไว้แล้ว และไม่ใช่เราเอง
        //        if ($conversation->assigned_employee_id &&
        //            (int) $conversation->assigned_employee_id !== (int) $employeeId) {
        //
        //            return response()->json([
        //                'message' => 'ห้องนี้ถูกพนักงานคนอื่นรับผิดชอบแล้ว',
        //            ], 409);
        //        }

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
            event(new LineOAChatConversationUpdated($conversationFresh));
            event(new LineOAConversationAssigned($conversationFresh));
        });

        return response()->json([
            'message' => 'success',
            'data' => $conversationFresh,
        ]);
    }

    public function assignees(): JsonResponse
    {
        // ดึงจากตาราง Admin หรือ Employee ตามโครงของโบ๊ท
        $items = Admin::query()
            ->where('enable', 'Y')
            ->orderBy('user_name')
            ->get([
                'code',
                'user_name',
                'name',
            ]);

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function assign(Request $request, LineConversation $conversation): JsonResponse
    {
        // อนุญาตให้เป็น null ได้ สำหรับเคส "ไม่มีผู้รับผิดชอบ"
        $data = $request->validate([
            'employee_id' => ['nullable', 'integer'],
        ]);

        $employeeId = $data['employee_id'] ?? null;

        // เคส: ไม่มีผู้รับผิดชอบ (กดตัวเลือกแรก)
        if ($employeeId === null) {
            $conversation->assigned_employee_id = null;
            $conversation->assigned_employee_name = null;
            $conversation->assigned_at = null;
            $conversation->save();

            $conversationFresh = $conversation->fresh([
                'contact.member',
                'account',
                'registerSessions' => function ($q) {
                    $q->where('status', 'in_progress');
                },
            ]) ?? $conversation;

            DB::afterCommit(function () use ($conversationFresh) {
                event(new LineOAChatConversationUpdated($conversationFresh));
                // จะยิง LineOAConversationAssigned ด้วยหรือเปล่า แล้วแต่คุณ:
                event(new LineOAConversationAssigned($conversationFresh));
            });

            return response()->json([
                'message' => 'success',
                'data' => $conversationFresh,
            ]);
        }

        // เคส: มีผู้รับผิดชอบเป็นพนักงานคนหนึ่ง
        /** @var \Gametech\Admin\Models\Admin|null $employee */
        $employee = Admin::find($employeeId);

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบผู้ใช้งานที่เลือก',
            ], 404);
        }

        // ตรงนี้คุณเลือกได้ว่าจะเก็บเป็น id หรือ code
        $conversation->assigned_employee_id = $employee->code; // หรือ $employee->id
        $conversation->assigned_employee_name = $employee->user_name ?? $employee->name;
        $conversation->assigned_at = now();
        $conversation->save();

        $conversationFresh = $conversation->fresh([
            'contact.member',
            'account',
            'registerSessions' => function ($q) {
                $q->where('status', 'in_progress');
            },
        ]) ?? $conversation;

        DB::afterCommit(function () use ($conversationFresh) {
            event(new LineOAChatConversationUpdated($conversationFresh));
            event(new LineOAConversationAssigned($conversationFresh));
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
     *   POST /admin/line-oa/conversations/{conversation}/lock
     */
    public function lock(Request $request, LineConversation $conversation): JsonResponse
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
            event(new LineOAChatConversationUpdated($conversationFresh));
            event(new LineOAConversationLocked($conversationFresh));
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
     *   POST /admin/line-oa/conversations/{conversation}/unlock
     */
    public function unlock(Request $request, LineConversation $conversation): JsonResponse
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
            event(new LineOAChatConversationUpdated($conversationFresh));
            event(new LineOAConversationLocked($conversationFresh)); // ใช้ event เดิม แต่ payload lock เป็น null
        });

        return response()->json([
            'message' => 'success',
            'data' => $conversationFresh,
        ]);
    }

    public function close(Request $request, LineConversation $conversation): JsonResponse
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
                event(new LineOAChatConversationUpdated($conversationFresh));
                event(new LineOAConversationClosed($conversationFresh));
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
            event(new LineOAChatConversationUpdated($conversationFresh));
            event(new LineOAConversationClosed($conversationFresh));
        });

        return response()->json([
            'message' => 'success',
            'data' => $conversationFresh,
        ]);
    }

    public function open(Request $request, LineConversation $conversation): JsonResponse
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
        $contactId = $conversation->line_contact_id;
        $accountId = $conversation->line_account_id;

        $existingOpen = LineConversation::query()
            ->where('line_contact_id', $contactId)
            ->where('line_account_id', $accountId)
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
                event(new LineOAChatConversationUpdated($conversationFresh));
                event(new LineOAConversationOpen($conversationFresh));
            });

            return response()->json([
                'message' => 'success',
                'data' => $conversationFresh,
            ]);
        }

        // เซตสถานะเป็น open (กลับไปสถานะเริ่มต้นหลังจากเสร็จสิ้น)
        $conversation->status = 'open';
        $conversation->closed_by_employee_id = null;
        $conversation->closed_by_employee_name = null;
        $conversation->closed_at = null;

        // ไม่บังคับล็อกห้องอัตโนมัติเมื่อกด Inbox
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
            event(new LineOAChatConversationUpdated($conversationFresh));
            event(new LineOAConversationOpen($conversationFresh));
        });

        return response()->json([
            'message' => 'success',
            'data' => $conversationFresh,
        ]);
    }

    public function cancelRegister(LineConversation $conversation)
    {
        // หา session ค้าง
        $session = LineRegisterSession::where('line_conversation_id', $conversation->id)
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

            event(new LineOAChatConversationUpdated($conversation));
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

        /** @var LineConversation|null $conversation */
        $conversation = LineConversation::query()
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
            Log::channel('line_oa')->warning('[LineOA] getBalance error', [
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

        /** @var LineConversation|null $conversation */
        $conversation = LineConversation::query()
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
            Log::channel('line_oa')->warning('[LineOA] getBalance error', [
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

    /**
     * แสดงรายการโน้ตของห้องสนทนา
     *
     * GET /line-oa/conversations/{conversation}/notes
     */
    public function listNotes(LineConversation $conversation): JsonResponse
    {
        // ตามหลักควรจะ check สิทธิ์ด้วย (แล้วแต่โบ๊ทใช้ Gate/Policy ไหม)
        // if (Gate::denies('view', $conversation)) {
        //     abort(403);
        // }

        $notes = LineConversationNote::query()
            ->where('line_conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->get([
                'id',
                'body',
                'employee_id',
                'employee_name',
                'created_at',
            ]);

        $data = $notes->map(function (LineConversationNote $note) {
            return [
                'id' => $note->id,
                'body' => $note->body,
                'employee_id' => $note->employee_id,
                'employee_name' => $note->employee_name,
                'created_at' => optional($note->created_at)->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * เพิ่มโน้ตใหม่ให้ห้องสนทนา
     *
     * POST /line-oa/conversations/{conversation}/notes
     *
     * body: { body: "ข้อความโน้ต" }
     */
    public function storeNote(Request $request, LineConversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        /** @var \Gametech\Admin\Models\Admin|null $employee */
        $employee = Auth::guard('admin')->user();

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        $employeeId = $employee->code ?? null;
        $employeeName = $employee->user_name ?? ($employee->name ?? 'พนักงาน');

        if (! $employeeId) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบรหัสพนักงาน (code)',
            ], 403);
        }

        $body = trim($data['body']);

        if ($body === '') {
            return response()->json([
                'success' => false,
                'message' => 'ข้อความโน้ตห้ามเว้นว่าง',
            ], 422);
        }

        /** @var LineConversationNote $note */
        $note = LineConversationNote::create([
            'line_conversation_id' => $conversation->id,
            'line_account_id' => $conversation->line_account_id,
            'line_contact_id' => $conversation->line_contact_id,
            'employee_id' => $employeeId,
            'employee_name' => $employeeName,
            'body' => $body,
        ]);

        $data = [
            'id' => $note->id,
            'body' => $note->body,
            'employee_id' => $note->employee_id,
            'employee_name' => $note->employee_name,
            'created_at' => optional($note->created_at)->toIso8601String(),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 201);
    }

    public function updateNote(
        Request $request,
        LineConversation $conversation,
        LineConversationNote $note
    ): JsonResponse {
        // ตรวจว่า note นี้อยู่ในห้องเดียวกันจริงไหม กันยิง cross-conversation
        if ((int) $note->line_conversation_id !== (int) $conversation->id) {
            return response()->json([
                'success' => false,
                'message' => 'โน้ตนี้ไม่ได้อยู่ในห้องสนทนานี้',
            ], 403);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        /** @var \Gametech\Admin\Models\Admin|null $employee */
        $employee = Auth::guard('admin')->user();

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        $employeeId = $employee->code ?? null;
        $employeeName = $employee->user_name ?? ($employee->name ?? 'พนักงาน');

        if (! $employeeId) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบรหัสพนักงาน (code)',
            ], 403);
        }

        $body = trim($data['body']);

        if ($body === '') {
            return response()->json([
                'success' => false,
                'message' => 'ข้อความโน้ตห้ามเว้นว่าง',
            ], 422);
        }

        // อัปเดตโน้ต
        $note->body = $body;
        $note->employee_id = $employeeId;
        $note->employee_name = $employeeName;
        $note->save();

        $resp = [
            'id' => $note->id,
            'body' => $note->body,
            'employee_id' => $note->employee_id,
            'employee_name' => $note->employee_name,
            'created_at' => optional($note->created_at)->toIso8601String(),
            // เผื่ออนาคต frontend อยากแสดงว่าแก้ไขเมื่อไหร่
            'updated_at' => optional($note->updated_at)->toIso8601String(),
        ];

        return response()->json([
            'success' => true,
            'data' => $resp,
        ]);
    }

    public function destroyNote(
        LineConversation $conversation,
        LineConversationNote $note
    ): JsonResponse {
        /** @var \Gametech\Admin\Models\Admin|null $employee */
        $employee = Auth::guard('admin')->user();

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        // กันยิงลบโน้ตข้ามห้อง
        if ((int) $note->line_conversation_id !== (int) $conversation->id) {
            return response()->json([
                'success' => false,
                'message' => 'โน้ตนี้ไม่ได้อยู่ในห้องสนทนานี้',
            ], 403);
        }

        $note->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบโน้ตสำเร็จ',
        ]);
    }

    /**
     * ปักหมุดห้องสนทนา (ฝั่งซ้าย)
     * POST /admin/line-oa/conversations/{conversation}/pin
     */
    public function pinConversation(LineConversation $conversation): JsonResponse
    {
        /** @var \Gametech\Admin\Models\Admin|null $employee */
        $employee = Auth::guard('admin')->user();

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        $conversation->is_pinned = true;
        $conversation->save();

        $conversationFresh = $conversation->fresh([
            'contact.member',
            'account',
            'registerSessions' => function ($q) {
                $q->where('status', 'in_progress');
            },
        ]) ?? $conversation;

        // broadcast ให้ list ซ้ายของทุกคนอัปเดต
        event(new LineOAChatConversationUpdated($conversationFresh));

        return response()->json([
            'success' => true,
            'data' => $conversationFresh,
        ]);
    }

    /**
     * เลิกปักหมุดห้องสนทนา
     * POST /admin/line-oa/conversations/{conversation}/unpin
     */
    public function unpinConversation(LineConversation $conversation): JsonResponse
    {
        /** @var \Gametech\Admin\Models\Admin|null $employee */
        $employee = Auth::guard('admin')->user();

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        $conversation->is_pinned = false;
        $conversation->save();

        $conversationFresh = $conversation->fresh([
            'contact.member',
            'account',
            'registerSessions' => function ($q) {
                $q->where('status', 'in_progress');
            },
        ]) ?? $conversation;

        event(new LineOAChatConversationUpdated($conversationFresh));

        return response()->json([
            'success' => true,
            'data' => $conversationFresh,
        ]);
    }

    /**
     * ปักหมุดข้อความในห้อง
     * POST /admin/line-oa/messages/{message}/pin
     */
    public function pinMessage(LineMessage $message): JsonResponse
    {
        /** @var \Gametech\Admin\Models\Admin|null $employee */
        $employee = Auth::guard('admin')->user();

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        // กันไม่ให้ไปปักห้องคนอื่น OA / กรณีอยากเช็คเพิ่ม สามารถตรวจผ่าน relation conversation/account ได้
        $message->is_pinned = true;
        $message->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $message->id,
                'line_conversation_id' => $message->line_conversation_id,
                'direction' => $message->direction,
                'source' => $message->source,
                'type' => $message->type,
                'text' => $message->text,
                'sent_at' => optional($message->sent_at)->toIso8601String(),
                'sender_employee_id' => $message->sender_employee_id,
                'sender_bot_key' => $message->sender_bot_key,
                'meta' => $message->meta,
                'payload' => $message->payload,
                'is_pinned' => (bool) $message->is_pinned,
            ],
        ]);
    }

    /**
     * เลิกปักหมุดข้อความในห้อง
     * POST /admin/line-oa/messages/{message}/unpin
     */
    public function unpinMessage(LineMessage $message): JsonResponse
    {
        /** @var \Gametech\Admin\Models\Admin|null $employee */
        $employee = Auth::guard('admin')->user();

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        $message->is_pinned = false;
        $message->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $message->id,
                'line_conversation_id' => $message->line_conversation_id,
                'direction' => $message->direction,
                'source' => $message->source,
                'type' => $message->type,
                'text' => $message->text,
                'sent_at' => optional($message->sent_at)->toIso8601String(),
                'sender_employee_id' => $message->sender_employee_id,
                'sender_bot_key' => $message->sender_bot_key,
                'meta' => $message->meta,
                'payload' => $message->payload,
                'is_pinned' => (bool) $message->is_pinned,
            ],
        ]);
    }
}
