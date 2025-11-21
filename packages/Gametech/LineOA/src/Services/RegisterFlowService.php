<?php

namespace Gametech\LineOA\Services;

use Gametech\LineOA\Contracts\LineMemberRegistrar;
use Gametech\LineOA\Models\LineContact;
use Gametech\LineOA\Models\LineConversation;
use Gametech\LineOA\Models\LineRegisterSession;
use Gametech\Marketing\Models\MarketingMember as Member;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * จัดการ flow การสมัครสมาชิกผ่าน LINE แบบถาม–ตอบทีละ step
 */
class RegisterFlowService
{
    public const STEP_PHONE = 'phone';

    public const STEP_NAME = 'name';

    public const STEP_SURNAME = 'surname';

    public const STEP_BANK = 'bank';

    public const STEP_ACCOUNT = 'account';

    public const STEP_FINISHED = 'finished';

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
     * ข้อความจากลูกค้าหนึ่งข้อความ ผ่านเข้ามาที่นี่
     */
    public function handleTextMessage(
        LineContact $contact,
        LineConversation $conversation,
        string $text
    ): ?RegisterFlowResult {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        // เริ่ม flow เมื่อพิมพ์ "สมัคร"
        if ($this->isStartKeyword($text)) {
            return $this->handleStart($contact, $conversation);
        }

        // หา session สมัครที่ in_progress อยู่
        $session = $this->getInProgressSession($contact);

        if (! $session) {
            return null;
        }

        // ยกเลิก
        if ($this->isCancelKeyword($text)) {
            return $this->handleCancel($session);
        }

        // เลือก handler ตาม step ปัจจุบัน
        switch ($session->current_step) {
            case self::STEP_PHONE:
                return $this->handlePhoneStep($session, $text);

            case self::STEP_NAME:
                return $this->handleNameStep($session, $text);

            case self::STEP_SURNAME:
                return $this->handleSurnameStep($session, $text);

            case self::STEP_BANK:
                return $this->handleBankStep($session, $text);

            case self::STEP_ACCOUNT:
                return $this->handleAccountStep($session, $text);

            default:
                return RegisterFlowResult::make()
                    ->handled(true)
                    ->finished(true)
                    ->session($session)
                    ->replyText(
                        $this->templates->render('register.already_completed')
                    );
        }
    }

    /**
     * เริ่ม flow ใหม่เมื่อพิมพ์ "สมัคร"
     */
    protected function handleStart(
        LineContact $contact,
        LineConversation $conversation
    ): RegisterFlowResult {
        // เคยสมัครสำเร็จแล้ว
        $existingCompleted = LineRegisterSession::query()
            ->where('line_contact_id', $contact->id)
            ->where('status', 'completed')
            ->first();

        if ($existingCompleted) {
            return RegisterFlowResult::make()
                ->handled(true)
                ->finished(true)
                ->replyText(
                    $this->templates->render('register.already_completed')
                );
        }

        // session ค้างอยู่
        $session = $this->getInProgressSession($contact);

        if (! $session) {
            $session = LineRegisterSession::create([
                'line_contact_id' => $contact->id,
                'line_conversation_id' => $conversation->id,
                'status' => 'in_progress',    // 👈 ตรงกับ migration
                'current_step' => self::STEP_PHONE,
                'data' => [],
            ]);
        } else {
            $session->current_step = self::STEP_PHONE;
            $session->data = [];
            $session->save();
        }

        $reply = $this->templates->render('register.ask_phone', [
            'contact_name' => $contact->display_name ?? '',
        ]);

        return RegisterFlowResult::make()
            ->handled(true)
            ->session($session)
            ->replyText($reply);
    }

    /**
     * STEP 1: เบอร์โทร
     */
    protected function handlePhoneStep(LineRegisterSession $session, string $text): RegisterFlowResult
    {
        $plain = $this->normalizePhone($text);

        if (! $plain) {
            $reply = $this->templates->render('register.error_phone_invalid', [
                'input' => $text,
            ]);

            return RegisterFlowResult::make()
                ->handled(true)
                ->session($session)
                ->replyText($reply);
        }

        // ใช้ rule จริงแบบเว็บ: ห้ามซ้ำใน members.tel และ banks_account.acc_no
        if ($this->isPhoneAlreadyUsed($plain)) {
            $reply = $this->templates->render('register.error_phone_used', [
                'phone' => $plain,
            ]);

            return RegisterFlowResult::make()
                ->handled(true)
                ->session($session)
                ->replyText($reply);
        }

        $data = $session->data ?? [];
        $data['phone'] = $plain;

        $session->data = $data;
        $session->current_step = self::STEP_NAME;
        $session->save();

        $reply = $this->templates->render('register.ask_name');

        return RegisterFlowResult::make()
            ->handled(true)
            ->session($session)
            ->replyText($reply);
    }

    /**
     * STEP 2: ชื่อจริง
     */
    protected function handleNameStep(LineRegisterSession $session, string $text): RegisterFlowResult
    {
        $name = trim($text);

        if ($name === '' || mb_strlen($name) < 2) {
            $reply = $this->templates->render('register.error_name_invalid');

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

        $reply = $this->templates->render('register.ask_surname');

        return RegisterFlowResult::make()
            ->handled(true)
            ->session($session)
            ->replyText($reply);
    }

    /**
     * STEP 3: นามสกุล
     */
    protected function handleSurnameStep(LineRegisterSession $session, string $text): RegisterFlowResult
    {
        $surname = trim($text);

        if ($surname === '' || mb_strlen($surname) < 2) {
            $reply = $this->templates->render('register.error_surname_invalid');

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
            ->replyText($reply)
            ->quickReply($this->getBankQuickReplyOptions());
    }

    /**
     * STEP 4: ธนาคาร
     *
     * รองรับทั้งพิมพ์ชื่อธนาคารเอง และกดจาก quick reply
     */
    protected function handleBankStep(LineRegisterSession $session, string $text): RegisterFlowResult
    {
        // map input → code กลาง เช่น KBANK / SCB / ...
        $bankCode = $this->normalizeBankInput($text);

        if (! $bankCode) {
            $reply = $this->templates->render('register.error_bank_invalid', [
                'input' => $text,
            ]);

            return RegisterFlowResult::make()
                ->handled(true)
                ->session($session)
                ->replyText($reply)
                ->quickReply($this->getBankQuickReplyOptions());
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
     * STEP 5: เลขบัญชี
     */
    protected function handleAccountStep(LineRegisterSession $session, string $text): RegisterFlowResult
    {
        $plain = $this->normalizeAccountNo($text);

        if (! $plain) {
            $reply = $this->templates->render('register.error_account_invalid', [
                'input' => $text,
            ]);

            return RegisterFlowResult::make()
                ->handled(true)
                ->session($session)
                ->replyText($reply);
        }

        $data = $session->data ?? [];
        $bankCode = Arr::get($data, 'bank_code');

        if (! $bankCode) {
            // state แปลก → ย้อนไปถามธนาคารใหม่
            $session->current_step = self::STEP_BANK;
            $session->save();

            $reply = $this->templates->render('register.ask_bank', [
                'name' => Arr::get($data, 'name'),
                'surname' => Arr::get($data, 'surname'),
            ]);

            return RegisterFlowResult::make()
                ->handled(true)
                ->session($session)
                ->replyText($reply)
                ->quickReply($this->getBankQuickReplyOptions());
        }

        // ห้ามซ้ำแบบเว็บ (แบบง่าย):
        // - members.acc_no
        // - banks_account.acc_no
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

        // เริ่มสมัครจริง
        try {
            $result = $this->memberRegistrar->registerFromLineData($data);
        } catch (\Throwable $e) {
            report($e);

            $reply = $this->templates->render('register.error_system', [
                'reason' => $e->getMessage(),
            ]);

            $session->status = 'failed';
            $session->error_message = $e->getMessage();
            $session->save();

            return RegisterFlowResult::make()
                ->handled(true)
                ->session($session)
                ->finished(true)
                ->replyText($reply);
        }

        if (! $result->success) {
            $session->status = 'failed';
            $session->error_message = $result->message;
            $session->save();

            $reply = $this->templates->render('register.error_system', [
                'reason' => $result->message,
            ]);

            return RegisterFlowResult::make()
                ->handled(true)
                ->session($session)
                ->finished(true)
                ->replyText($reply);
        }

        // สมัครสำเร็จ
        $session->status = 'completed';
        $session->current_step = self::STEP_FINISHED;
        $session->member_id = $result->memberId;
        $session->save();

        $reply = $this->templates->render('register.complete_success', [
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
    }

    protected function handleCancel(LineRegisterSession $session): RegisterFlowResult
    {
        $session->status = 'cancelled';
        $session->current_step = self::STEP_FINISHED;
        $session->error_message = null;
        $session->save();

        $reply = $this->templates->render('register.cancelled');

        return RegisterFlowResult::make()
            ->handled(true)
            ->session($session)
            ->finished(true)
            ->replyText($reply);
    }

    /**
     * หา session ที่สถานะ in_progress ของ contact นี้
     */
    protected function getInProgressSession(LineContact $contact): ?LineRegisterSession
    {
        return LineRegisterSession::query()
            ->where('line_contact_id', $contact->id)
            ->where('status', 'in_progress')
            ->orderByDesc('id')
            ->first();
    }

    protected function isStartKeyword(string $text): bool
    {
        $text = trim(mb_strtolower($text));

        $keywords = [
            'สมัคร',
            'สมัครสมาชิก',
            'reg',
            'register',
        ];

        return in_array($text, $keywords, true);
    }

    protected function isCancelKeyword(string $text): bool
    {
        $text = trim(mb_strtolower($text));

        $keywords = [
            'ยกเลิก',
            'ยกเลิกสมัคร',
            'cancel',
            'stop',
        ];

        return in_array($text, $keywords, true);
    }

    protected function normalizePhone(string $text): ?string
    {
        $digits = preg_replace('/\D+/', '', $text);

        if (mb_strlen($digits) !== 10) {
            return null;
        }

        if (! preg_match('/^0[0-9]{9}$/', $digits)) {
            return null;
        }

        return $digits;
    }

    /**
     * ปล่อยให้ bank_code ตรงกับค่าที่เว็บใช้ (เผื่อรองรับเคสส่งตัวเลขตรง ๆ)
     */
    protected function normalizeBankCode(string $text): ?string
    {
        $t = trim($text);

        if ($t === '') {
            return null;
        }

        return $t;
    }

    /**
     * ตัวเลือกธนาคารที่จะแสดงเป็น Quick Reply ใน LINE
     *
     * โครงสร้าง domain เป็นกลาง ๆ:
     * [
     *   ['label' => 'กสิกรไทย',   'text' => 'กสิกรไทย'],
     *   ['label' => 'ไทยพาณิชย์', 'text' => 'ไทยพาณิชย์'],
     *   ...
     * ]
     */
    protected function getBankQuickReplyOptions(): array
    {
        return [
            [
                'label' => 'กสิกรไทย',
                'text' => 'กสิกรไทย',
            ],
            [
                'label' => 'ไทยพาณิชย์',
                'text' => 'ไทยพาณิชย์',
            ],
            [
                'label' => 'กรุงไทย',
                'text' => 'กรุงไทย',
            ],
            [
                'label' => 'กรุงเทพ',
                'text' => 'กรุงเทพ',
            ],
            [
                'label' => 'กรุงศรี',
                'text' => 'กรุงศรี',
            ],
            [
                'label' => 'ออมสิน',
                'text' => 'ออมสิน',
            ],
            [
                'label' => 'TTB',
                'text' => 'TTB',
            ],
        ];
    }

    /**
     * แปลง input ธนาคารจากข้อความ → bank_code กลาง
     *
     * NOTE: ตอนนี้ใช้โค้ด KBANK/SCB/KTB/... เป็นกลาง ๆ
     *       เวลาไปสมัครจริงใน DefaultLineMemberRegistrar
     *       ค่อย map จาก code เหล่านี้ไปเป็น bank_code ของระบบ (ตัวเลข)
     */
    protected function normalizeBankInput(string $text): ?string
    {
        $t = Str::lower(preg_replace('/\s+/', '', $text));

        $map = [
            // กสิกรไทย
            'กสิกรไทย' => 'KBANK',
            'กสิกร' => 'KBANK',
            'kbank' => 'KBANK',
            'kasikorn' => 'KBANK',

            // ไทยพาณิชย์
            'ไทยพาณิชย์' => 'SCB',
            'scb' => 'SCB',

            // กรุงไทย
            'กรุงไทย' => 'KTB',
            'ktb' => 'KTB',

            // กรุงเทพ
            'กรุงเทพ' => 'BBL',
            'bangkokbank' => 'BBL',
            'bbl' => 'BBL',

            // กรุงศรี
            'กรุงศรี' => 'BAY',
            'bay' => 'BAY',

            // ทหารไทย / TMB / TTB
            'ttb' => 'TTB',
            'tmb' => 'TTB',
            'ทหารไทย' => 'TTB',

            // ออมสิน
            'ออมสิน' => 'GSB',
            'gsb' => 'GSB',
        ];

        // ตรงเป๊ะก่อน
        if (isset($map[$t])) {
            return $map[$t];
        }

        // เผื่อพิมพ์คำอื่นยาว ๆ ที่มีคำเหล่านี้อยู่
        foreach ($map as $k => $code) {
            if (Str::contains($t, $k)) {
                return $code;
            }
        }

        return null;
    }

    protected function normalizeAccountNo(string $text): ?string
    {
        $digits = preg_replace('/\D+/', '', $text);

        if (mb_strlen($digits) < 6 || mb_strlen($digits) > 16) {
            return null;
        }

        return $digits;
    }

    protected function isPhoneAlreadyUsed(string $phone): bool
    {
        // 1) members.tel
        if (Member::where('tel', $phone)->exists()) {
            return true;
        }

        // 2) banks_account.acc_no
        $existsInBankAccount = DB::table('banks_account')
            ->where('acc_no', $phone)
            ->exists();

        return $existsInBankAccount;
    }

    protected function isBankAccountAlreadyUsed(?string $bankCode, string $accountNo): bool
    {
        // ตอนนี้ bankCode เป็นโค้ด KBANK/SCB/... เลยเช็คจากเลขบัญชีเป็นหลัก
        $dupMember = Member::where('acc_no', $accountNo)->exists();

        if ($dupMember) {
            return true;
        }

        $existsInBankAccount = DB::table('banks_account')
            ->where('acc_no', $accountNo)
            ->exists();

        return $existsInBankAccount;
    }
}

/**
 * DTO สำหรับผลลัพธ์ของ RegisterFlowService
 */
class RegisterFlowResult
{
    public bool $handled = false;

    public bool $finished = false;

    public ?int $memberId = null;

    public ?string $replyText = null;

    public ?LineRegisterSession $session = null;

    /** ตัวเลือก quick reply (เช่น เลือกธนาคาร) */
    public ?array $quickReply = null;

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

    public function replyText(?string $replyText): self
    {
        $this->replyText = $replyText;

        return $this;
    }

    public function session(?LineRegisterSession $session): self
    {
        $this->session = $session;

        return $this;
    }

    /** เซ็ตตัวเลือก quick reply (เช่น ใช้ตอนถามธนาคาร) */
    public function quickReply(?array $options): self
    {
        $this->quickReply = $options;

        return $this;
    }
}
