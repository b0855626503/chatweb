<?php

namespace Gametech\LineOA\Services;

use Gametech\LineOA\Contracts\LineMemberRegistrar;
use Gametech\LineOA\Models\LineContact;
use Gametech\LineOA\Models\LineConversation;
use Gametech\LineOA\Models\LineRegisterSession;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * จัดการ flow การสมัครสมาชิกผ่าน LINE แบบถาม–ตอบทีละ step
 *
 * แนวคิด:
 * - ใช้ LineRegisterSession เก็บ state ของการสมัคร 1 รอบต่อ line_contact
 * - ทุกข้อความจาก user ที่เกี่ยวกับสมัครจะวิ่งผ่าน service นี้
 * - ใช้ LineTemplateService ดึงข้อความตอบกลับ (แก้ได้จากหลังบ้าน)
 * - ใช้ LineMemberRegistrar ในการสร้างสมาชิกจริงในระบบหลัก
 */
class RegisterFlowService
{
    // ชื่อ step ต่าง ๆ ใน flow สมัคร
    public const STEP_PHONE = 'phone';

    public const STEP_NAME = 'name';

    public const STEP_SURNAME = 'surname';

    public const STEP_BANK = 'bank';

    public const STEP_ACCOUNT = 'account';

    public const STEP_DONE = 'done';

    // สถานะ session
    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_FAILED = 'failed';

    /**
     * คำที่ถือว่าเป็น trigger เริ่มสมัคร
     * สามารถไปปรับให้โหลดจาก config/DB ได้ในอนาคต
     *
     * @var string[]
     */
    protected array $registerTriggers = [
        'สมัคร',
        'สมัครสมาชิก',
        'regis',
        'register',
    ];

    protected LineTemplateService $templates;

    protected LineMemberRegistrar $memberRegistrar;

    public function __construct(
        LineTemplateService $templates,
        LineMemberRegistrar $memberRegistrar
    ) {
        $this->templates = $templates;
        $this->memberRegistrar = $memberRegistrar;
    }

    /**
     * handle ข้อความ text จาก LINE
     *
     * - ถ้ายังไม่มี session และข้อความเป็น trigger → เริ่ม flow ใหม่
     * - ถ้ามี session in_progress → เดินต่อ step ที่ค้างอยู่
     * - ถ้าไม่เกี่ยวกับสมัครเลย → return null
     */
    public function handleTextMessage(
        LineContact $contact,
        LineConversation $conversation,
        string $text
    ): ?RegisterFlowResult {
        $text = trim($text);

        // 1) หา session ที่กำลังสมัครอยู่ (in_progress)
        $session = $this->findActiveSession($contact);

        // 2) ถ้าไม่มี session และข้อความไม่ใช่ trigger → service นี้ไม่รับผิดชอบ
        if (! $session && ! $this->isRegisterTrigger($text)) {
            return null;
        }

        // 3) ถ้าไม่มี session แต่เป็น trigger → เริ่ม flow ใหม่
        if (! $session) {
            $session = $this->startNewSession($contact, $conversation);

            $reply = $this->templates->render('register.ask_phone', [
                'contact_name' => $contact->display_name ?? '',
            ]);

            return RegisterFlowResult::make()
                ->handled(true)
                ->session($session)
                ->replyText($reply);
        }

        // 4) ถ้ามี session อยู่แล้ว ให้ดูว่าเป็นคำสั่งยกเลิกไหม
        if ($this->isCancelCommand($text)) {
            $session->status = self::STATUS_CANCELLED;
            $session->current_step = self::STEP_DONE;
            $session->save();

            $reply = $this->templates->render('register.cancelled', [
                'contact_name' => $contact->display_name ?? '',
            ]);

            return RegisterFlowResult::make()
                ->handled(true)
                ->session($session)
                ->finished(true)
                ->replyText($reply);
        }

        // 5) เดินตาม step ปัจจุบัน
        switch ($session->current_step) {
            case self::STEP_PHONE:
                return $this->handlePhoneStep($session, $contact, $text);

            case self::STEP_NAME:
                return $this->handleNameStep($session, $contact, $text);

            case self::STEP_SURNAME:
                return $this->handleSurnameStep($session, $contact, $text);

            case self::STEP_BANK:
                return $this->handleBankStep($session, $contact, $text);

            case self::STEP_ACCOUNT:
                return $this->handleAccountStep($session, $contact, $text);

            case self::STEP_DONE:
            default:
                // ถ้า session จบแล้ว แต่ user ยังพิมพ์อะไรมาเกี่ยวกับ "สมัคร"
                // สามารถตอบแนว "คุณสมัครเสร็จแล้ว" ได้
                $reply = $this->templates->render('register.already_completed', [
                    'contact_name' => $contact->display_name ?? '',
                ]);

                return RegisterFlowResult::make()
                    ->handled(true)
                    ->session($session)
                    ->finished(true)
                    ->replyText($reply);
        }
    }

    /**
     * หา session สมัครที่ยัง active ของ contact นี้
     */
    protected function findActiveSession(LineContact $contact): ?LineRegisterSession
    {
        return LineRegisterSession::query()
            ->where('line_contact_id', $contact->id)
            ->where('status', self::STATUS_IN_PROGRESS)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * เริ่ม session สมัครใหม่
     */
    protected function startNewSession(
        LineContact $contact,
        LineConversation $conversation
    ): LineRegisterSession {
        return LineRegisterSession::create([
            'line_contact_id' => $contact->id,
            'line_conversation_id' => $conversation->id,
            'status' => self::STATUS_IN_PROGRESS,
            'current_step' => self::STEP_PHONE,
            'data' => [],
        ]);
    }

    /**
     * ตรวจว่า text นี้เป็น trigger เริ่มสมัครไหม
     */
    protected function isRegisterTrigger(string $text): bool
    {
        $t = Str::lower($text);

        foreach ($this->registerTriggers as $trigger) {
            if (Str::contains($t, Str::lower($trigger))) {
                return true;
            }
        }

        return false;
    }

    /**
     * ตรวจว่าเป็นคำสั่งยกเลิกไหม
     */
    protected function isCancelCommand(string $text): bool
    {
        $t = Str::lower(trim($text));

        return in_array($t, ['ยกเลิก', 'cancel', 'ยกเลิกสมัคร'], true);
    }

    /**
     * STEP: ขอเบอร์โทร และ validate
     */
    protected function handlePhoneStep(
        LineRegisterSession $session,
        LineContact $contact,
        string $text
    ): RegisterFlowResult {
        $plain = preg_replace('/\D+/', '', $text ?? '');

        // Format ผิด
        if (strlen($plain) !== 10) {
            $reply = $this->templates->render('register.error_phone_invalid', [
                'input' => $text,
            ]);

            return RegisterFlowResult::make()
                ->handled(true)
                ->session($session)
                ->replyText($reply);
        }

        // เช็กซ้ำเบอร์ในระบบสมาชิก
        if ($this->isPhoneAlreadyUsed($plain)) {
            $reply = $this->templates->render('register.error_phone_used', [
                'phone' => $plain,
            ]);

            return RegisterFlowResult::make()
                ->handled(true)
                ->session($session)
                ->replyText($reply);
        }

        // ผ่านทั้งคู่ → เก็บใน data แล้วไป step ถัดไป
        $data = $session->data ?? [];
        $data['phone'] = $plain;

        $session->data = $data;
        $session->current_step = self::STEP_NAME;
        $session->save();

        $reply = $this->templates->render('register.ask_name', [
            'phone' => $plain,
        ]);

        return RegisterFlowResult::make()
            ->handled(true)
            ->session($session)
            ->replyText($reply);
    }

    /**
     * STEP: ชื่อจริง
     */
    protected function handleNameStep(
        LineRegisterSession $session,
        LineContact $contact,
        string $text
    ): RegisterFlowResult {
        $name = trim($text);

        if ($name === '' || mb_strlen($name) < 2) {
            $reply = $this->templates->render('register.error_name_invalid', [
                'input' => $text,
            ]);

            return RegisterFlowResult::make()
                ->handled(true)
                ->session($session)
                ->replyText($reply);
        }

        $data = $session->data ?? [];
        $data['name'] = $name;

        $session->data = $data;
        $session->current_step = self::STEP_SURNAME;
        $session->save();

        $reply = $this->templates->render('register.ask_surname', [
            'name' => $name,
        ]);

        return RegisterFlowResult::make()
            ->handled(true)
            ->session($session)
            ->replyText($reply);
    }

    /**
     * STEP: นามสกุล
     */
    protected function handleSurnameStep(
        LineRegisterSession $session,
        LineContact $contact,
        string $text
    ): RegisterFlowResult {
        $surname = trim($text);

        if ($surname === '' || mb_strlen($surname) < 2) {
            $reply = $this->templates->render('register.error_surname_invalid', [
                'input' => $text,
            ]);

            return RegisterFlowResult::make()
                ->handled(true)
                ->session($session)
                ->replyText($reply);
        }

        $data = $session->data ?? [];
        $data['surname'] = $surname;

        $session->data = $data;
        $session->current_step = self::STEP_BANK;
        $session->save();

        $reply = $this->templates->render('register.ask_bank', [
            'name' => Arr::get($data, 'name'),
            'surname' => $surname,
        ]);

        return RegisterFlowResult::make()
            ->handled(true)
            ->session($session)
            ->replyText($reply);
    }

    /**
     * STEP: ธนาคาร
     *
     * หมายเหตุ: ตอนจริงน่าจะใช้ quick reply / postback จะดีกว่าพิมพ์เอา
     */
    protected function handleBankStep(
        LineRegisterSession $session,
        LineContact $contact,
        string $text
    ): RegisterFlowResult {
        $bankCode = $this->normalizeBankInput($text);

        if (! $bankCode) {
            $reply = $this->templates->render('register.error_bank_invalid', [
                'input' => $text,
            ]);

            return RegisterFlowResult::make()
                ->handled(true)
                ->session($session)
                ->replyText($reply);
        }

        $data = $session->data ?? [];
        $data['bank_code'] = $bankCode;

        $session->data = $data;
        $session->current_step = self::STEP_ACCOUNT;
        $session->save();

        $reply = $this->templates->render('register.ask_account', [
            'bank_code' => $bankCode,
        ]);

        return RegisterFlowResult::make()
            ->handled(true)
            ->session($session)
            ->replyText($reply);
    }

    /**
     * STEP: เลขบัญชี
     */
    protected function handleAccountStep(
        LineRegisterSession $session,
        LineContact $contact,
        string $text
    ): RegisterFlowResult {
        $plain = preg_replace('/\D+/', '', $text ?? '');

        if (strlen($plain) < 6) {
            $reply = $this->templates->render('register.error_account_invalid', [
                'input' => $text,
            ]);

            return RegisterFlowResult::make()
                ->handled(true)
                ->session($session)
                ->replyText($reply);
        }

        // เช็กซ้ำเลขบัญชีในระบบสมาชิก
        $bankCode = Arr::get($session->data ?? [], 'bank_code');

        if ($this->isBankAccountAlreadyUsed($bankCode, $plain)) {
            $reply = $this->templates->render('register.error_account_used', [
                'account_no' => $plain,
            ]);

            return RegisterFlowResult::make()
                ->handled(true)
                ->session($session)
                ->replyText($reply);
        }

        $data = $session->data ?? [];
        $data['account_no'] = $plain;

        $session->data = $data;

        // ถึงจุดนี้ข้อมูลครบแล้ว → ลองสร้าง member จริง
        return $this->finalizeRegistration($session, $contact);
    }

    /**
     * ปิดจบ flow: เรียก service สมัครสมาชิกจริง แล้วส่งข้อความสมัครสำเร็จ
     */
    protected function finalizeRegistration(
        LineRegisterSession $session,
        LineContact $contact
    ): RegisterFlowResult {
        // กัน case เดินซ้ำ
        if ($session->status === self::STATUS_COMPLETED) {
            $reply = $this->templates->render('register.already_completed', [
                'contact_name' => $contact->display_name ?? '',
            ]);

            return RegisterFlowResult::make()
                ->handled(true)
                ->session($session)
                ->finished(true)
                ->replyText($reply);
        }

        $data = $session->data ?? [];

        // ทำใน transaction กัน credit/ข้อมูลเพี้ยน
        return DB::transaction(function () use ($session, $contact, $data) {

            $result = $this->memberRegistrar->registerFromLineData([
                'phone' => Arr::get($data, 'phone'),
                'name' => Arr::get($data, 'name'),
                'surname' => Arr::get($data, 'surname'),
                'bank_code' => Arr::get($data, 'bank_code'),
                'account_no' => Arr::get($data, 'account_no'),
                'line_contact_id' => $contact->id,
            ]);

            if (! $result->success) {
                $session->status = self::STATUS_FAILED;
                $session->error_message = $result->message ?? 'REGISTER_FAILED';
                $session->save();

                $reply = $this->templates->render('register.error_system', [
                    'reason' => $result->message ?? '',
                ]);

                return RegisterFlowResult::make()
                    ->handled(true)
                    ->session($session)
                    ->finished(true)
                    ->replyText($reply);
            }

            // สมัครสำเร็จ
            $session->status = self::STATUS_COMPLETED;
            $session->member_id = $result->memberId;
            $session->current_step = self::STEP_DONE;
            $session->save();

            // ผูก member กับ line_contact
            $contact->member_id = $result->memberId;
            $contact->save();

            $reply = $this->templates->render('register.complete_success', [
                'contact_name' => $contact->display_name ?? '',
                'username' => $result->username,
                'password' => $result->password,
                'login_url' => $result->loginUrl,
            ]);

            return RegisterFlowResult::make()
                ->handled(true)
                ->session($session)
                ->finished(true)
                ->memberId($result->memberId)
                ->replyText($reply);
        });
    }

    /**
     * แปลง input ธนาคารจากข้อความ → bank_code
     *
     * NOTE: ตรงนี้โบ๊ทต้องไปผูกกับ table/logic ของระบบจริงนะ
     */
    protected function normalizeBankInput(string $text): ?string
    {
        $t = Str::lower(preg_replace('/\s+/', '', $text));

        $map = [
            'kbank' => 'KBANK',
            'กสิกร' => 'KBANK',
            'ไทยพาณิชย์' => 'SCB',
            'scb' => 'SCB',
            'กรุงไทย' => 'KTB',
            'ktb' => 'KTB',
            'กรุงเทพ' => 'BBL',
            'bbl' => 'BBL',
            'กรุงศรี' => 'BAY',
            'bay' => 'BAY',
        ];

        foreach ($map as $k => $code) {
            if (Str::contains($t, $k)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * เช็กว่าเบอร์นี้ถูกใช้แล้วในระบบสมาชิกหรือยัง
     *
     * NOTE: โบ๊ทต้อง implement จริงให้เรียก model/member table ของระบบ
     */
    protected function isPhoneAlreadyUsed(string $phone): bool
    {
        // TODO: แก้ให้เรียก members table จริง
        // return Member::where('tel', $phone)->exists();
        return false;
    }

    /**
     * เช็กว่าเลขบัญชีนี้ถูกใช้แล้วหรือยัง (ภายใต้ bank เดียวกัน)
     *
     * NOTE: โบ๊ทต้องผูกกับ table บัญชีสมาชิกจริง
     */
    protected function isBankAccountAlreadyUsed(?string $bankCode, string $accountNo): bool
    {
        // TODO: แก้ให้เรียก bank accounts table จริง
        // return MemberBank::where('bank_code', $bankCode)
        //     ->where('account_no', $accountNo)
        //     ->exists();
        return false;
    }
}

/**
 * ผลลัพธ์จาก RegisterFlowService
 * - handled: ข้อความนี้ถูก flow สมัครจัดการหรือไม่
 * - finished: flow สมัครรอบนี้จบแล้วหรือยัง
 * - replyText: ข้อความหลักที่ควรส่งตอบกลับ (TEXT)
 *   (โบ๊ทจะขยายเป็น messages หลายประเภทในอนาคตได้)
 */
class RegisterFlowResult
{
    public bool $handled = false;

    public bool $finished = false;

    public ?int $memberId = null;

    public ?string $replyText = null;

    public ?LineRegisterSession $session = null;

    public static function make(): self
    {
        return new self;
    }

    public function handled(bool $handled): self
    {
        $this->handled = $handled;

        return $this;
    }

    public function finished(bool $finished): self
    {
        $this->finished = $finished;

        return $this;
    }

    public function memberId(?int $memberId): self
    {
        $this->memberId = $memberId;

        return $this;
    }

    public function session(LineRegisterSession $session): self
    {
        $this->session = $session;

        return $this;
    }

    public function replyText(string $text): self
    {
        $this->replyText = $text;

        return $this;
    }
}
