<?php

namespace Gametech\Auto\Jobs;

use Gametech\Core\Models\AllLogProxy;
use Gametech\Payment\Repositories\PaymentPromotionRepository;
use Gametech\Payment\Repositories\BankPaymentRepository;
use Gametech\Payment\Repositories\BankAccountRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class TopupPayments implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** ระยะเวลาที่ job นี้ถือสิทธิ์ uniqueness (วินาที) */
    public int $uniqueFor = 10;

    /** timeout ระดับ job (วินาที) */
    public int $timeout = 60;

    /** จำนวน retry ทั้งหมด (กำหนดให้ชัดเจน เลี่ยง default ไม่สิ้นสุด) */
    public int $tries = 1;

    /** จำนวน exception สูงสุดก่อนถือว่าล้มเหลว */
    public int $maxExceptions = 3;

    /** payment primary key (code) */
    protected string|int $paymentId;

    /** ค่าคอนฟิกระบบ (snapshot ตอน dispatch) */
    protected object $config;

    public function __construct(string|int $payment)
    {
        $this->paymentId = $payment;
        $this->config = core()->getConfigData();
    }

    public function tags(): array
    {
        return ['render', 'topup:' . $this->paymentId];
    }

    public function uniqueId(): string
    {
        return (string) $this->paymentId;
    }

    public function handle(): bool
    {
        /** @var BankPaymentRepository $bankPaymentRepo */
        $bankPaymentRepo = app(BankPaymentRepository::class);
        /** @var PaymentPromotionRepository $paymentPromoRepo */
        $paymentPromoRepo = app(PaymentPromotionRepository::class);
        /** @var BankAccountRepository $bankAccountRepo */
        $bankAccountRepo  = app(BankAccountRepository::class);

        $payment = $bankPaymentRepo->findOneByField('code', $this->paymentId);
        if (!$payment) {
            // ไม่พบรายการ → จบแบบเงียบ ๆ
            return false;
        }

        // ── 1) Idempotent guard: ถ้าสถานะไม่ใช่ "รอเติม" หรือยังไม่ผ่าน autocheck → ไม่ต้องทำต่อ
        // status: 0=ยังไม่เติม, 1=เติมแล้ว, 2=ปฏิเสธ
        if ((int)$payment->status !== 0 || (string)$payment->autocheck !== 'W') {
            return true; // ถือว่าผ่าน (ไม่มีอะไรต้องทำ)
        }

        // ── 2) ตรวจขั้นต่ำการฝาก (เฉพาะกรณียังไม่มี emp_topup)
        if ((int)$payment->emp_topup === 0) {
            $bankAccount = $bankAccountRepo->findOneByField('code', $payment->account_code);

            // ถ้าหาบัญชีไม่เจอ → กันพังและถือว่าเคสไม่พร้อมประมวลผล
            if (!$bankAccount) {
                // ไม่แก้สถานะ เพื่อให้แก้ข้อมูลแล้วรันใหม่ได้
                return false;
            }

            $min = (float) ($bankAccount->deposit_min > 0
                ? $bankAccount->deposit_min
                : ($this->config->deposit_min ?? 0));

            if ($min > 0 && (float)$payment->value < $min) {
                // ปิดการประมวลผลอัตโนมัติของรายการนี้ เนื่องจากต่ำกว่าขั้นต่ำ
                $payment->autocheck   = 'Y';
                $payment->remark_admin = 'ยอดฝากไม่ถึงขั้นต่ำ (' . $min . ')';
                $payment->topup_by     = 'System Auto';
                $payment->saveQuietly();
                return false;
            }
        }

        // ── 3) ถ้ามีบันทึก AllLogProxy ตรงรายการนี้แล้ว → ถือว่าเติมแล้ว (กันเติมซ้ำ)
        $hasLog = AllLogProxy::where('bank_payment_id', $payment->code)->exists();
        if ($hasLog) {
            $payment->autocheck = 'Y';
            $payment->status    = 1;
            $payment->saveQuietly();
            return true;
        }

        // ── 4) ทางแยกเติมเครดิตตามโหมดระบบ
        // หมายเหตุ: repository เหล่านี้ควรมี idempotency ภายใน (กันเติมซ้ำในระดับ DB)
        if ((string)$this->config->seamless === 'Y') {
            $paymentPromoRepo->checkFastStartSeamless(
                (float) $payment->value,
                (int)   $payment->member_topup,
                (string)$payment->code
            );
            $bankPaymentRepo->refillPaymentSeamless(collect($payment)->toArray());
            return true;
        }

        if ((string)$this->config->multigame_open === 'Y') {
            $paymentPromoRepo->checkFastStart(
                (float) $payment->value,
                (int)   $payment->member_topup,
                (string)$payment->code
            );
            $bankPaymentRepo->refillPayment(collect($payment)->toArray());
            return true;
        }

        // single
        $paymentPromoRepo->checkFastStartSingle(
            (float) $payment->value,
            (int)   $payment->member_topup,
            (string)$payment->code
        );
        $bankPaymentRepo->refillPaymentSingle(collect($payment)->toArray());

        return true;
    }

    public function failed(Throwable $e): void
    {
        // ส่งต่อให้ระบบ exception handler ของแอป
        report($e);
    }
}
