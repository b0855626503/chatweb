<?php

namespace Gametech\LineOA\Http\Controllers\Admin;

use Gametech\Admin\Http\Controllers\AppBaseController;
use Gametech\Game\Repositories\GameUserRepository;
use Gametech\LineOA\DataTables\TopupDataTable;
use Gametech\LineOA\Events\LineOAChatConversationUpdated;
use Gametech\LineOA\Events\LineOAConversationAssigned;
use Gametech\LineOA\Events\LineOAConversationClosed;
use Gametech\LineOA\Events\LineOAConversationLocked;
use Gametech\LineOA\Events\LineOAConversationOpen;
use Gametech\LineOA\Models\LineContact;
use Gametech\LineOA\Models\LineConversation;
use Gametech\LineOA\Models\LineMessage;
use Gametech\LineOA\Models\LineRegisterSession;
use Gametech\LineOA\Services\ChatService;
use Gametech\LineOA\Services\LineMessagingClient;
use Gametech\LineOA\Services\RegisterFlowService;
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
    public function page(TopupDataTable $topupDataTable)
    {
        // ให้ได้ตัว Html\Builder แบบเดียวกับตอนใช้ ->render()
        $depositTable = $topupDataTable->html();

        return view('admin::module.line-oa.index', [
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

        } else {
            // “ยังไม่ปิดเคส”
            $query->where(function ($qBuilder) {
                $qBuilder->whereNull('status')
                    ->orWhereIn('status', ['open', 'assigned']);
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

        if ($conversation->locked_by_employee_id && $conversation->locked_by_employee_id != $employeeId) {
            return response()->json([
                'message' => 'ห้องนี้ถูกล็อกโดย '.($conversation->locked_by_employee_name ?: 'พนักงานคนอื่น').' คุณไม่สามารถตอบได้',
            ], 403);
        }

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
        $lineText = $text;

        $meta = $message->meta;
        if (is_array($meta)) {
            $outboundTrans = $meta['translation_outbound'] ?? null;

            if (is_array($outboundTrans) && ! empty($outboundTrans['translated_text'])) {
                $lineText = $outboundTrans['translated_text'];
            }
        }

        if ($account && $contact && $contact->line_user_id) {
            $result = $this->lineMessaging->pushText(
                $account,
                $contact->line_user_id,
                $lineText        // ← เปลี่ยนมาใช้ตัวนี้
            );

            if (! $result['success']) {
                Log::warning('[LineChat] ส่งข้อความไป LINE ไม่สำเร็จ', [
                    'conversation_id' => $conversation->id,
                    'contact_id' => $contact->id,
                    'error' => $result['error'] ?? null,
                    'status' => $result['status'] ?? null,
                ]);
            }
        } else {
            Log::warning('[LineChat] ไม่สามารถส่งข้อความไป LINE ได้ (ไม่พบ account/contact/line_user_id)', [
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
    public function replyImage(Request $request, LineConversation $conversation): JsonResponse
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

        if ($conversation->locked_by_employee_id && $conversation->locked_by_employee_id != $employeeId) {
            return response()->json([
                'message' => 'ห้องนี้ถูกล็อกโดย '.($conversation->locked_by_employee_name ?: 'พนักงานคนอื่น').' คุณไม่สามารถตอบได้',
            ], 403);
        }

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

            if (! $result['success']) {
                Log::warning('[LineChat] ส่งรูปไป LINE ไม่สำเร็จ', [
                    'conversation_id' => $conversation->id,
                    'contact_id' => $contact->id,
                    'image_url' => $originalUrl,
                    'error' => $result['error'] ?? null,
                    'status' => $result['status'] ?? null,
                ]);
            }
        } else {
            Log::warning('[LineChat] ไม่สามารถส่งรูปไป LINE ได้ (ไม่พบ account/contact/line_user_id หรือ url ว่าง)', [
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
                    Log::warning('[LineChat] ดึง content รูปจาก LINE ไม่สำเร็จ', [
                        'message_id' => $message->id,
                        'line_message_id' => $message->line_message_id,
                    ]);
                }
            }

            // 4) สุดท้ายถ้าไม่มีอะไรเลย → 404
            Log::warning('[LineChat] ไม่พบ content รูปสำหรับ message', [
                'message_id' => $message->id,
                'line_message_id' => $message->line_message_id,
            ]);

            abort(404);
        } catch (\Throwable $e) {
            Log::error('[LineChat] exception ใน content()', [
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
            Log::error('[LineOA] findMember error', [
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

    public function registerMember(Request $request): JsonResponse
    {
        try {
            /** @var \Gametech\LineOA\Services\RegisterFlowService $flow */
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
                'created_from' => 'line_staff', // ระบุว่ามาจาก Support Staff
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
            Log::error('[LineOA] registerMember error', [
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

        if ($memberId === '') {
            return response()->json([
                'message' => 'member_id ห้ามว่าง',
            ], 422);
        }

        // ดึงข้อมูล member มาใส่เพิ่ม (optional)
        $memberName = null;
        $memberUsername = null;
        $memberMobile = null;
        $memberBankName = null;
        $memberAccNo = null;

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
            Log::warning('[LineOA] attachMember: cannot load member detail', [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);
        }

        // เตรียม payload สำหรับอัปเดตทุก LineContact ที่มี line_user_id เดียวกัน
        $update = [
            'member_id' => $memberId,
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
                'ok'      => false,
                'message' => 'ไม่พบค่า conversation_id',
            ], 422);
        }

        /** @var LineConversation|null $conversation */
        $conversation = LineConversation::query()
            ->with('contact')
            ->find($conversationId);

        if (! $conversation) {
            return response()->json([
                'ok'      => false,
                'message' => 'ไม่พบห้องสนทนา',
            ], 404);
        }

        // ดึง member จาก contact
        $memberId       = $conversation->contact?->member_id;
        $memberUsername = $conversation->contact?->member_username;

        if (! $memberId) {
            return response()->json([
                'ok'      => false,
                'message' => 'ห้องนี้ยังไม่ได้ผูกกับสมาชิกในระบบ',
            ], 422);
        }

        $member = $memberRepository->find($memberId);

        if (! $member) {
            return response()->json([
                'ok'      => false,
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
            $success = is_array($rawResponse) ? (bool)($rawResponse['success'] ?? false) : false;

            if ($success) {
                $score   = $rawResponse['score'] ?? 0;
                $balance = (float) $score;
            } else {
                // ดึง message จาก provider ถ้ามี
                $providerMessage = is_array($rawResponse)
                    ? ($rawResponse['message'] ?? 'ไม่สามารถดึงยอดเงินจากผู้ให้บริการได้')
                    : 'ไม่สามารถดึงยอดเงินจากผู้ให้บริการได้';

                return response()->json([
                    'ok'      => false,
                    'message' => $providerMessage,
                ], 502);
            }
        } catch (Throwable $e) {
            // log ไว้เผื่อ debug
            Log::warning('[LineOA] getBalance error', [
                'conversation_id' => $conversationId,
                'member_id'       => $memberId,
                'response'        => $rawResponse,
                'error'           => $e->getMessage(),
            ]);

            return response()->json([
                'ok'      => false,
                'message' => 'เกิดข้อผิดพลาดระหว่างดึงยอดเงิน',
            ], 500);
        }

        return response()->json([
            'ok'      => true,
            'message' => 'success',
            'data'    => [
                'member_id'       => $memberId,
                'member_username' => $memberUsername,
                'member_gameuser' => $member->game_user,
                'balance'         => $balance,
                'balance_text'    => number_format($balance, 2),
                'currency'        => 'THB',
            ],
        ]);
    }
}
