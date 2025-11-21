<?php

namespace Gametech\LineOA\Http\Controllers\Admin;

use Gametech\LineOa\Models\LineConversation;
use Gametech\LineOa\Models\LineMessage;
use Gametech\LineOa\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    protected ChatService $chat;

    public function __construct(ChatService $chat)
    {
        $this->chat = $chat;

        // ถ้าอยากกันสิทธิ์ ฝั่งนี้ควรผ่าน middleware admin อยู่แล้วจาก route group
        // $this->middleware('admin');
    }

    /**
     * GET /admin/line-oa/conversations
     *
     * คืน list ห้องแชต (เอาไว้แสดงใน sidebar ซ้าย)
     *
     * Query params ที่รองรับ (optional):
     * - status: open/closed (default: open)
     * - q: keyword เพื่อค้นหา display_name / member_username / member_mobile
     * - account_id: filter ตาม OA
     * - per_page: จำนวนต่อหน้า (default 20)
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->get('status', 'open');
        $accountId = $request->get('account_id');
        $q = trim((string) $request->get('q', ''));
        $perPage = (int) $request->get('per_page', 20);

        $query = LineConversation::query()
            ->with(['contact', 'account'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($status) {
            $query->where('status', $status);
        }

        if ($accountId) {
            $query->where('line_account_id', $accountId);
        }

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
                    'last_message_at' => optional($conv->last_message_at)->toDateTimeString(),
                    'unread_count' => $conv->unread_count,
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
     * GET /admin/line-oa/conversations/{conversation}
     *
     * คืนรายละเอียดห้อง + messages ล่าสุด
     *
     * Query params ที่รองรับ:
     * - limit: ดึงกี่ข้อความ (default 50)
     * - before_id: ถ้าอยากโหลดย้อนหลัง (pagination แบบ chat)
     */
    public function show(Request $request, LineConversation $conversation): JsonResponse
    {
        $limit = (int) $request->get('limit', 50);
        $beforeId = $request->get('before_id');

        $conversation->load(['contact', 'account']);

        $messagesQuery = LineMessage::query()
            ->where('line_conversation_id', $conversation->id)
            ->orderByDesc('id');

        if ($beforeId) {
            $messagesQuery->where('id', '<', $beforeId);
        }

        $messages = $messagesQuery
            ->limit($limit)
            ->get()
            ->reverse() // ให้เรียงจากเก่า -> ใหม่ เวลาโชว์ใน UI
            ->values();

        // mark ห้องนี้ว่า unread = 0 เมื่อแอดมินเปิดอ่าน
        if ($conversation->unread_count > 0) {
            $conversation->unread_count = 0;
            $conversation->save();
        }

        $data = [
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
                'last_message_at' => optional($conversation->last_message_at)->toDateTimeString(),
                'unread_count' => $conversation->unread_count,
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
                ],
            ],
            'messages' => $messages->map(function (LineMessage $m) {
                return [
                    'id' => $m->id,
                    'direction' => $m->direction,
                    'source' => $m->source,
                    'type' => $m->type,
                    'text' => $m->text,
                    'sent_at' => optional($m->sent_at)->toDateTimeString(),
                    'sender_employee_id' => $m->sender_employee_id,
                    'sender_bot_key' => $m->sender_bot_key,
                    'meta' => $m->meta,
                ];
            }),
        ];

        return response()->json($data);
    }

    /**
     * POST /admin/line-oa/conversations/{conversation}/reply
     *
     * ส่งข้อความตอบลูกค้าจากฝั่งพนักงาน
     *
     * body:
     *  - text: string (required)
     *
     * NOTE: method นี้:
     *  1) บันทึกข้อความ outbound ลง line_messages (ผ่าน ChatService)
     *  2) TODO: ยิงข้อความไปที่ LINE จริง ๆ ผ่าน LineMessagingClient
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

        // หา employee id ปัจจุบัน
        // ปรับตรงนี้ตามระบบจริงของโบ๊ทว่าตัวไหนคือ primary key ของ employee
        $employee = Auth::guard('admin')->user();
        $employeeId = $employee?->id ?? null;

        if (! $employeeId) {
            return response()->json([
                'message' => 'ไม่พบข้อมูลผู้ใช้งาน (admin)',
            ], 403);
        }

        // 1) บันทึก message outbound ฝั่งระบบ
        $message = $this->chat->createOutboundMessageFromAgent(
            $conversation,
            $text,
            $employeeId,
            [
                'employee_name' => $employee->name ?? null,
            ]
        );

        // 2) TODO: ยิงข้อความออกไปยัง LINE จริง ๆ
        //    - ใช้ LineAccount ของ conversation -> access_token
        //    - ใช้ LineMessagingClient (service อีกตัว) -> push/pushMessage
        //
        //    ตัวอย่างโครง (ยังไม่ได้ implement class จริง):
        //
        //    $account = $conversation->account;
        //    $contact = $conversation->contact;
        //    $this->lineMessaging->sendText(
        //        $account,
        //        $contact->line_user_id,
        //        $text
        //    );

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
            ],
        ]);
    }
}
