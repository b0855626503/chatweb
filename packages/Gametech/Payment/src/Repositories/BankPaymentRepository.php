<?php

namespace Gametech\Payment\Repositories;

use App\Notifications\RealTimeNotification;
use Carbon\Carbon;
use DateTime;
use Gametech\Core\Eloquent\Repository;
use Gametech\Core\Repositories\AllLogRepository;
use Gametech\Game\Repositories\GameUserRepository;
use Gametech\LogAdmin\Http\Traits\ActivityLogger;
use Gametech\LogUser\Http\Traits\ActivityLoggerUser;
use Gametech\Member\Repositories\MemberCreditLogRepository;
use Gametech\Member\Repositories\MemberDiamondLogRepository;
use Gametech\Member\Repositories\MemberPointLogRepository;
use Gametech\Member\Repositories\MemberPromotionLogRepository;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Member\Repositories\MemberSelectProRepository;
use Gametech\Payment\Models\BankAccount;
use Gametech\Promotion\Repositories\PromotionRepository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Notification;
use Throwable;

class BankPaymentRepository extends Repository
{
    use ActivityLogger;
    use ActivityLoggerUser;

    private $memberRepository;

    private $memberCreditLogRepository;

    private $memberPromotionLogRepository;

    private $allLogRepository;

    private $paymentPromotionRepository;

    private $promotionRepository;

    private $bankAccountRepository;

    private $memberPointLogRepository;

    private $memberSelectProRepository;

    private $memberDiamondLogRepository;

    private $gameUserRepository;

    public function __construct(
        MemberRepository             $memberRepo,
        MemberCreditLogRepository    $memberCreditLogRepo,
        AllLogRepository             $allLogRepo,
        PaymentPromotionRepository   $paymentPromotionRepo,
        PromotionRepository          $promotionRepo,
        BankAccountRepository        $bankAccountRepo,
        MemberPointLogRepository     $memberPointLogRepo,
        MemberDiamondLogRepository   $memberDiamondLogRepo,
        GameUserRepository           $gameUserRepo,
        MemberPromotionLogRepository $memberPromotionLogRepo,
        MemberSelectProRepository    $memberSelectProRepo,
        App                          $app
    )
    {
        $this->memberRepository = $memberRepo;

        $this->memberCreditLogRepository = $memberCreditLogRepo;

        $this->allLogRepository = $allLogRepo;

        $this->paymentPromotionRepository = $paymentPromotionRepo;

        $this->promotionRepository = $promotionRepo;

        $this->bankAccountRepository = $bankAccountRepo;

        $this->memberPointLogRepository = $memberPointLogRepo;

        $this->memberSelectProRepository = $memberSelectProRepo;

        $this->memberDiamondLogRepository = $memberDiamondLogRepo;

        $this->gameUserRepository = $gameUserRepo;

        $this->memberPromotionLogRepository = $memberPromotionLogRepo;

        parent::__construct($app);
    }

    public function loadDeposit($id, $date_start = null, $date_stop = null)
    {
        return $this->with('promotion')->orderBy('date_create', 'desc')->findWhere(['member_topup' => $id, 'enable' => 'Y', ['value', '>', 0]])
            ->when($date_start, function ($query, $date_start) use ($date_stop) {
                return $query->whereRaw("DATE_FORMAT(date_create,'%Y-%m-%d') between ? and ?", [$date_start, $date_stop]);
            });

    }

    public function checkPayment_($limit = 5, $bank = 'tw')
    {

        return $this->when($bank, function ($query, $bank) {
            if ($bank === 'tw') {
                return $query->select(['bank_payment.tx_hash', 'bank_payment.*'])->distinct('tx_hash');
            } else {
                return $query->select(['bank_payment.tx_hash', 'bank_payment.*'])->distinct('tx_hash');

            }
        })->orderBy('code', 'asc')
            ->waiting()->active()->income()->where('tx_hash', '!=', '')
            ->where('bankstatus', 1)
            ->where('autocheck', 'N')
//            ->whereIn('create_by', ['SYSAUTO','BAYAUTO1','BAYAUTO2','BAYAUTO3','BAYAUTO4','BAYAUTO5'])
            ->whereNotIn('create_by', ['SCBAUTO1', 'SCBAUTO2', 'SCBAUTO3', 'SCBAUTO4', 'SCBAUTO5', 'TOPUPSCBAUTO1', 'TOPUPSCBAUTO2', 'TOPUPSCBAUTO3', 'TOPUPSCBAUTO4', 'TOPUPSCBAUTO5', 'KBANKAUTO1', 'KBANKAUTO2', 'KBANKAUTO3', 'KBANKAUTO4', 'KBANKAUTO5'])
            ->with('bank_account')
            ->whereHas('bank_account', function ($model) use ($bank) {
                $model->active()->topup()->in()->with('bank')->whereHas('bank', function ($model) use ($bank) {
                    $model->where('shortcode', strtoupper($bank));
                });
            })
            ->limit($limit)->get();

    }

    public function checkPayment($limit = 5, $bank = 'tw')
    {
        return $this->scopeQuery(function ($query) use ($limit, $bank) {
            return $query->orderBy('bank_time', 'asc')->orderBy('code', 'asc')
                ->waiting()->active()->income()
                ->where('bankstatus', 1)
                ->where('autocheck', 'N')
                ->where('member_topup', 0)
                // ✅ มี bank_account เท่านั้น และต้อง active + in + status_topup = 'Y'
                ->whereHas('bank_account', function ($q) use ($bank) {
                    $q->active()->in()->topup()->whereHas('bank', function ($model) use ($bank) {
                        $model->where('shortcode', strtoupper($bank));
                    });
                })
                ->limit($limit);
        })
            // ✅ eager load ให้สอดคล้องกับเงื่อนไขเดียวกัน
            ->with(['bank_account' => function ($q) {
                $q->active()->in()->topup()->with('bank');
            }])
            ->all();
    }

    public function loadPayment($limit = 5)
    {
        return $this->scopeQuery(function ($query) use ($limit) {
            return $query->orderBy('bank_time', 'asc')->orderBy('code', 'asc')
                ->waiting()->active()->income()
                ->where('bankstatus', 1)
                ->where('autocheck', 'W')
                ->where('member_topup', '<>', 0)
                // ✅ มี bank_account เท่านั้น และต้อง active + in + status_topup = 'Y'
                ->whereHas('bank_account', function ($q) {
                    $q->active()->in()->topup(); // topup() = status_topup = 'Y'
                })
                ->limit($limit);
        })
            // ✅ eager load ให้สอดคล้องกับเงื่อนไขเดียวกัน
            ->with(['bank_account' => function ($q) {
                $q->active()->in()->topup()->with('bank');
            }])
            ->all();
    }


    protected function isBetweenDates(string $start, string $end, string $current = null): bool
    {
        $startDate   = new DateTime($start);
        $endDate     = new DateTime($end);
        $currentDate = $current ? new DateTime($current) : new DateTime();

        return $currentDate >= $startDate && $currentDate <= $endDate;
    }

    public function isActiveNow($row, string $tz = 'Asia/Bangkok'): bool
    {
        $now = Carbon::now($tz);

        // รองรับชื่อ end/stop ได้ทั้งคู่
        $dateEnd = $row->date_end ?? $row->date_stop ?? null;
        $timeEnd = $row->time_end ?? $row->time_stop ?? null;

        $start = Carbon::parse(
            trim(($row->date_start ?? '') . ' ' . ($row->time_start ?? '00:00:00')),
            $tz
        );

        // ถ้าไม่ระบุเวลาสิ้นสุด → ปิดท้ายวัน 23:59:59
        $end = ($dateEnd)
            ? Carbon::parse(trim($dateEnd . ' ' . ($timeEnd ?: '23:59:59')), $tz)
            : null;

        // inclusive ทั้งหัว-ท้าย
        return $now->gte($start) && (is_null($end) || $now->lte($end));
    }

    public function refillPaymentSingle($data): bool
    {
        $ip = request()->ip();

        $today = now()->toDateString();
        $datenow = now()->toDateTimeString();

        $config = core()->getConfigData();
        $special = false;

        $payment = $this->find($data['code']);
        if (!$payment) {
            return false;
        }

        $member = $this->memberRepository->find($data['member_topup']);
        if (!$member) {
            return false;
        }

        $bank_acc = $this->bankAccountRepository->find($data['account_code']);
        if (!$bank_acc) {
            return false;
        }

        $game = core()->getGame();
        $game_user = $this->gameUserRepository->findOneWhere(['member_code' => $member->code, 'game_code' => $game->code, 'enable' => 'Y']);
        $game_code = $game->code;
        $user_name = $game_user->user_name;
        $user_code = $game_user->code;
        $game_name = $game->name;
        $game_balance = $game_user->balance;
        $member_code = $member->code;
        $amount = $data['value'];

        $selectpro = $this->memberSelectProRepository->findOneWhere(['member_code' => $member_code]);
        if ($selectpro) {
            ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'มีการเลือกโปรโมชั่น โปรรหัส '.$selectpro->pro_code, $member->code);

            if ($game_user->balance <= $config->pro_reset) {

                ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'ยอดเงินปัจจุบัน น้อยกวา่าโปรรีเซต ผ่านเงื่าอนไข โปรรหัส '.$selectpro->pro_code, $member->code);

                $promotion = $this->promotionRepository->checkSelectPro($selectpro->pro_code, $member_code, $amount, $datenow);
                $bonus = $promotion['bonus'];
                $pro_code = $promotion['pro_code'];
                $pro_name = $promotion['pro_name'];
                $total = $promotion['total'];
                $status_pro = 1;
                $turnpro = $promotion['turnpro'];
                $withdraw_limit = $promotion['withdraw_limit'];
                $withdraw_limit_rate = $promotion['withdraw_limit_rate'];
            } else {
                ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'ยอดเงินปัจจุบัน มากกวา่าโปรรีเซต ผิด เ้งื่อินไข โปรรหัส '.$selectpro->pro_code, $member->code);

                $bonus = 0;
                $pro_code = 0;
                $pro_name = '';
                $total = $amount;
                $status_pro = $member['status_pro'];
                $turnpro = 0;
                $withdraw_limit = 0;
                $withdraw_limit_rate = 0;
            }
            $selectpro->delete();
        } else {

            ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'ไม่ได้กดรับโปร ', $member->code);

            $bonus = 0;
            $pro_code = 0;
            $pro_name = '';
            $total = $amount;
            $status_pro = $member['status_pro'];
            $turnpro = 0;
            $withdraw_limit = 0;
            $withdraw_limit_rate = 0;
        }

        ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'เชคเงื่อนไข พิเศษ ของ บช ที่เติมเข้ามา '.$bank_acc->acc_no, $member->code);

        if ($bank_acc->bonus > 0) {

            ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'พบมี โบนัสเพิ่ม '.$bank_acc->bonus.' ของ บช ที่เติมเข้ามา '.$bank_acc->acc_no, $member->code);

            $now = now(); // ตาม app.timezone

            $isActive = $this->isBetweenDates($bank_acc->start_at,$bank_acc->end_at,$now);
            ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'ตรวจสอบ ช่วงเวลา ที่กำหนด ของระบุไว้ ในบช ที่เติมเข้ามา '.$bank_acc->acc_no, $member->code);

            if ($isActive) {
                ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'ยังอยู่ใมนช่วงเวลากิจกรรม โบนัสเพิื่ม '.$bank_acc->bonus.' ในบช ที่เติมเข้ามา '.$bank_acc->acc_no, $member->code);

                if ($pro_code === 0) {
                    $bonus = ($amount * $bank_acc->bonus) / 100;
                    if ($bank_acc->bonus_max > 0) {
                        if ($bonus > $bank_acc->bonus_max) {
                            $bonus = $bank_acc->bonus_max;
                        }
                    }

                    ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'คำนวนจากยอดฝาก '.$amount.' โบนัสเพิื่ม '.$bank_acc->bonus.'% ได้ โบนัส '.$bonus.' ในบช ที่เติมเข้ามา '.$bank_acc->acc_no, $member->code);

                    $pro_name = "ช่วงเวลา พิเศษ รับยอดเพิ่มขึ้น " . $bank_acc->bonus . "% จากยอดฝาก";
                    $total = ($total + $bonus);
                    $special = true;


                }


            }
        }else{
            ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'พบ ไม่มี โบนัสเพิ่ม ของ บช ที่เติมเข้ามา '.$bank_acc->acc_no, $member->code);

        }

        $point = 0;
        $diamond = 0;
        $count_deposit = 1;

        $bank_code = $bank_acc->bank->code;

        $credit_before = $game_balance;
        $credit_after = ($credit_before + $total);

        $chk = $this->allLogRepository->findOneByField('bank_payment_id', $data['code']);
        if ($chk) {
            ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'พบรายการเติมเงิน นี้ในระบบแล้ว', $member->code);

            return false;
        }

        try {

            $alllog = $this->allLogRepository->create([
                'before_credit' => $credit_before,
                'after_credit' => $credit_after,
                'status_log' => 0,
                'pro_id' => $pro_code,
                'pro_amount' => $bonus,
                'bonus' => $bonus,
                'game_code' => $game_code,
                'type_record' => 0,
                'gamebalance' => $game_balance,
                'member_code' => $member_code,
                'member_user' => $member['user_name'],
                'amount' => $amount,
                'bank_payment_id' => $data['code'],
                'ip' => $ip,
                'username' => $user_name,
                'remark' => '',
                'user_create' => 'System Auto',
                'user_update' => 'System Auto',
            ]);

        } catch (Throwable $e) {
            ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'ไม่สามารถ เพิ่มรายการ all log ได้');
            report($e);

            return false;
        }

        $money_text = 'User ' . $member->user_name . ' Game ID : ' . $user_name . ' จำนวนเงิน ' . $amount . ' โบนัส ' . $bonus . ' จากโปร ' . $pro_name . ' รวมเป็น ' . $total;

        ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'เริ่มรายการเติมเงิน ให้กับ User : ' . $member->user_name . ' Game ID : ' . $user_name);
        ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], $money_text);

        $response = $this->gameUserRepository->UserDeposit($game_code, $user_name, $total, false);

        if ($response['success'] === true) {
            ActivityLoggerUser::activity('Single ฝากเงินเข้าเกม ' . $game_name, $money_text . ' ระบบทำการฝากเงินเข้าเกมแล้ว ยอด ก่อน ' . $response['before'] . ' ยอดหลัง ' . $response['after']);
        } else {
            ActivityLoggerUser::activity('Single ฝากเงินเข้าเกม ' . $game_name, $money_text . ' ไม่สามารถฝากเงินเข้าเกมได้');

            return false;
        }

        DB::beginTransaction();

        try {

            $chknew = $this->memberCreditLogRepository->findOneWhere(['member_code' => $member_code, 'refer_code' => $data['code'], 'refer_table' => 'bank_payment', 'kind' => 'TOPUP']);
            if ($chknew) {
                ActivityLoggerUser::activity('Single ฝากเงินเข้าเกม ' . $game_name, $money_text . ' หยุดการทำงาน เนื่องจาก Log ซ้ำ');

                return false;
            }


            if ($special) {
                $remark = " ช่วงเวลาสุดพิเศษ " . $bank_acc->start_at . ' ถึง ' . $bank_acc->end_at . " รับเพิ่ม " . $bank_acc->bonus . "% อิงรรายการฝาก ID " . $data['code'];

            } else {
                $remark = 'เติมเงินฝากอ้างอิงรายการฝาก ID : ' . $data['code'] . ' RefID : ' . $response['ref_id'];
            }

            $bill = $this->memberCreditLogRepository->create([
                'ip' => $ip,
                'credit_type' => 'D',
                'game_code' => $game_code,
                'gameuser_code' => $user_code,
                'amount' => $amount,
                'bonus' => $bonus,
                'total' => $total,
                'balance_before' => 0,
                'balance_after' => 0,
                'credit' => $amount,
                'credit_bonus' => $bonus,
                'credit_total' => $total,
                'credit_before' => $response['before'],
                'credit_after' => $response['after'],
                'member_code' => $member_code,
                'user_name' => $member->user_name,
                'pro_code' => $pro_code,
                'pro_name' => $pro_name,
                'bank_code' => $bank_code,
                'refer_code' => $data['code'],
                'refer_table' => 'bank_payment',
                'emp_code' => $data['emp_topup'],
                'auto' => 'Y',
                'remark' => $remark,
                'kind' => 'TOPUP',
                'user_create' => 'System Auto',
                'user_update' => 'System Auto',
            ]);

            if ($special) {
                $this->memberCreditLogRepository->create([
                    'ip' => $ip,
                    'credit_type' => 'D',
                    'game_code' => $game_code,
                    'gameuser_code' => $user_code,
                    'amount' => 0,
                    'bonus' => $bonus,
                    'total' => 0,
                    'balance_before' => 0,
                    'balance_after' => 0,
                    'credit' => 0,
                    'credit_bonus' => $bonus,
                    'credit_total' => 0,
                    'credit_before' => 0,
                    'credit_after' => 0,
                    'member_code' => $member_code,
                    'user_name' => $member->user_name,
                    'pro_code' => $pro_code,
                    'pro_name' => $pro_name,
                    'bank_code' => $bank_code,
                    'refer_code' => $data['code'],
                    'refer_table' => 'bank_payment',
                    'emp_code' => $data['emp_topup'],
                    'auto' => 'Y',
                    'remark' => "อ้างอิงรายการฝากที่   ID : " . $data['code'] . " RefID : " . $response['ref_id'] ." ได้โบนัส จากช่องทางการฝากที่กำหนด เพิ่ม " . $bank_acc->bonus . " %",
                    'kind' => 'G_BONUS',
                    'user_create' => 'System Auto',
                    'user_update' => 'System Auto',
                ]);

            }

            $billcode = 0;

            $alllog->remark = 'Auto Topup and Refer Credit Log ID : ' . $bill->code;
            $alllog->user_update = 'System Auto';
            $alllog->save();

            if ($config->point_open == 'Y') {

                if ($config->point_per_bill == 'N') {

                    if ($amount >= $config->points && $config->points > 0) {
                        $point = floor($amount / $config->points);

                        $this->memberPointLogRepository->create([
                            'point_type' => 'D',
                            'point_amount' => $point,
                            'point_before' => $member->point_deposit,
                            'point_balance' => ($member->point_deposit + $point),
                            'member_code' => $member_code,
                            'remark' => 'ได้รับ Point จากการเติมเงิน ' . $amount . ' บาท เติม ' . $config->points . ' ได้รับ 1 แต้ม สรุปได้รับ ' . $point,
                            'emp_code' => $data['emp_topup'],
                            'ip' => $ip,
                            'user_create' => 'System Auto',
                            'user_update' => 'System Auto',
                        ]);
                    }

                } else {

                    if ($amount >= $config->points_topup && $config->points_topup > 0 && $config->points_amount > 0) {
                        $point = $config->points_amount;

                        $this->memberPointLogRepository->create([
                            'point_type' => 'D',
                            'point_amount' => $point,
                            'point_before' => $member->point_deposit,
                            'point_balance' => ($member->point_deposit + $point),
                            'member_code' => $member_code,
                            'remark' => 'ได้รับ Point จากการเติมเงิน ' . $amount . ' บาท ประเภทนับเป็นบิล เติมยอดมากกว่าหรือเท่ากับ ' . $config->points_topup . ' ได้รับ ' . $point . ' แต้ม',
                            'emp_code' => $data['emp_topup'],
                            'ip' => $ip,
                            'user_create' => 'System Auto',
                            'user_update' => 'System Auto',
                        ]);

                    }

                }

            }

            if ($config->diamond_open == 'Y') {

                if ($config->diamond_per_bill == 'N') {

                    if ($amount >= $config->diamonds && $config->diamonds > 0) {
                        $diamond = floor($amount / $config->diamonds);

                        $this->memberDiamondLogRepository->create([
                            'diamond_type' => 'D',
                            'diamond_amount' => $diamond,
                            'diamond_before' => $member->diamond,
                            'diamond_balance' => ($member->diamond + $diamond),
                            'member_code' => $member_code,
                            'remark' => 'ได้รับเพชร จากการเติมเงิน ' . $amount . ' บาท เติม ' . $config->diamonds . ' ได้รับ 1 เม็ด สรุปได้รับ ' . $diamond,
                            'emp_code' => $data['emp_topup'],
                            'ip' => $ip,
                            'user_create' => 'System Auto',
                            'user_update' => 'System Auto',
                        ]);
                    }

                } else {

                    if ($amount >= $config->diamonds_topup && $config->diamonds_topup > 0 && $config->diamonds_amount > 0) {
                        $diamond = $config->diamonds_amount;

                        $this->memberDiamondLogRepository->create([
                            'diamond_type' => 'D',
                            'diamond_amount' => $diamond,
                            'diamond_before' => $member->diamond,
                            'diamond_balance' => ($member->diamond + $diamond),
                            'member_code' => $member_code,
                            'remark' => 'ได้รับเพชร จากการเติมเงิน ' . $amount . ' บาท ประเภทนับเป็นบิล เติมยอดมากกว่าหรือเท่ากับ ' . $config->diamonds_topup . ' ได้รับ ' . $diamond . ' เม็ด',
                            'emp_code' => $data['emp_topup'],
                            'ip' => $ip,
                            'user_create' => 'System Auto',
                            'user_update' => 'System Auto',
                        ]);

                    }

                }

            }

            $payment->user_id = $user_name;
            $payment->status = 1;
            $payment->before_credit = $response['before'];
            $payment->after_credit = $response['after'];
            $payment->pro_id = $pro_code;
            $payment->amount = $amount;
            $payment->pro_amount = $bonus;
            $payment->score = $total;
            $payment->date_topup = $datenow;
            $payment->date_approve = $datenow;
            $payment->autocheck = 'Y';
            $payment->remark_admin = $payment->remark_admin . ' (เติมแล้ว)';
            $payment->topup_by = 'System Auto';
            $payment->ip_topup = $ip;
            $payment->save();

            $bills = app('Gametech\Payment\Repositories\BillRepository')->create([
                'complete' => 'Y',
                'enable' => 'Y',
                'refer_code' => $data['code'],
                'refer_table' => 'bank_payment',
                'ref_id' => $response['ref_id'],
                'credit_before' => $response['before'],
                'credit_after' => $response['after'],
                'member_code' => $member_code,
                'game_code' => $game_code,
                'gameuser_code' => $user_code,
                'pro_code' => $pro_code,
                'pro_name' => $pro_name,
                'method' => 'TOPUP',
                'transfer_type' => 1,
                'amount' => $amount,
                'balance_before' => $response['before'],
                'balance_after' => $response['after'],
                'credit' => $amount,
                'credit_bonus' => $bonus,
                'credit_balance' => $total,
                'amount_request' => ($credit_before + ($total * $turnpro)),
                'amount_limit' => ($credit_before + (($amount + $bonus) * $withdraw_limit_rate)),
                'ip' => $ip,
                'user_create' => $member['name'],
                'user_update' => $member['name'],
            ]);

            if ($pro_code > 0) {

                $this->memberPromotionLogRepository->create([
                    'date_start' => now()->toDateString(),
                    'bill_code' => $bills->code,
                    'member_code' => $member_code,
                    'game_code' => $game_code,
                    'game_name' => $game_name,
                    'gameuser_code' => $user_code,
                    'pro_code' => $pro_code,
                    'pro_name' => $pro_name,
                    'turnpro' => $turnpro,
                    'balance' => ($response['before'] - $amount),
                    'amount' => $amount,
                    'bonus' => $bonus,
                    'amount_balance' => ($total * $turnpro),
                    'total_amount_balance' => (($response['before'] - $amount) + ($total * $turnpro)),
                    'withdraw_limit' => $withdraw_limit,
                    'withdraw_limit_rate' => 0,
                    'complete' => 'N',
                    'enable' => 'Y',
                    'user_create' => $member['name'],
                    'user_update' => $member['name'],
                ]);

                $billcode = $bills->code;

                $game_user->balance = $response['after'];
                $game_user->pro_code = $pro_code;
                $game_user->bill_code = $billcode;
                $game_user->turnpro = $turnpro;
                $game_user->amount = $amount;
                $game_user->bonus = $bonus;
                $game_user->amount_balance += ($credit_before + ($total * $turnpro));
                $game_user->withdraw_limit = $withdraw_limit;
                $game_user->withdraw_limit_rate = $withdraw_limit_rate;
                $game_user->withdraw_limit_amount += ($credit_before + (($amount + $bonus) * $withdraw_limit_rate));
                $game_user->save();

            } else {

                if ($game_user->amount_balance > 0 || $game_user->pro_code > 0) {
                    if ($response['before'] > $config->pro_reset) {
                        $game_user->amount_balance += ($total * $game_user->turnpro);
                        $game_user->withdraw_limit_amount += ($total * $game_user->withdraw_limit_rate);
                        $game_user->save();
                    } else {
                        $game_user->balance = $response['after'];
                        $game_user->pro_code = 0;
                        $game_user->bill_code = 0;
                        $game_user->turnpro = 0;
                        $game_user->amount = 0;
                        $game_user->bonus = 0;
                        $game_user->amount_balance = 0;
                        $game_user->withdraw_limit = 0;
                        $game_user->withdraw_limit_rate = 0;
                        $game_user->withdraw_limit_amount = 0;
                        $game_user->save();
                    }
                }

            }

            $bill->amount_balance = $game_user->amount_balance;
            $bill->withdraw_limit = $game_user->withdraw_limit;
            $bill->withdraw_limit_amount = $game_user->withdraw_limit_amount;
            $bill->save();

            $member->credit += $amount;
            $member->sum_deposit += $amount;
            $member->status_pro = $status_pro;
            $member->point_deposit += $point;
            $member->diamond += $diamond;
            $member->balance = $response['after'];
            $member->count_deposit += $count_deposit;
            $member->save();

            DB::commit();

        } catch (Throwable $e) {
            ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'พบปัญหาใน Transaction');
            DB::rollBack();
            ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'Rollback Transaction');

            $response = $this->gameUserRepository->UserWithdraw($game_code, $user_name, $total);
            if ($response['success'] === true) {
                ActivityLoggerUser::activity('Single ฝากเงินเข้าเกม ' . $game_name, $money_text . ' ระบบทำการถอนเงินออกจากเกมแล้ว');

            } else {
                ActivityLoggerUser::activity('Single ฝากเงินเข้าเกม ' . $game_name, $money_text . ' ระบบไม่สามารถถอนเงินออกจากเกมได้');
            }
            $this->allLogRepository->where('bank_payment_id ', $data['code'])->delete();
            report($e);

            return false;

        }

        ActivityLoggerUser::activity('Single Topup ID : ' . $data['code'], 'เติมเงินสำเร็จให้กับ User : ' . $member->user_name);
        Notification::send($member, new RealTimeNotification(Lang::get('app.topup.complete') . $total));

        $account = $payment->bank_account;

        $sumToday = app('Gametech\Payment\Repositories\BankPaymentRepository')->income()->active()->complete()->where('account_code', $payment->account_code)->whereDate('date_topup', $today)->sum('value');
        $account->update(['sum_deposit' => $sumToday]);

        if ($account->sum_limit > 0) {
            if ($sumToday >= $account->sum_limit) {
                $account->update(['display_wallet' => 'N']);
                $alt = BankAccount::where('banks', $account->banks)
                    ->where('bank_type', 1)
                    ->where('display_wallet', 'N')
                    ->where('status_auto', 'Y')
                    ->where('enable', 'Y')
                    ->where('sum_deposit', 0)
                    ->orderBy('sort', 'asc')
                    ->first();

                if ($alt) {
                    $alt->update(['display_wallet' => 'Y']);
                }
            }
        }

        return true;

    }

    public function refillPaymentSeamless($data): bool
    {
        $ip = request()->ip();

        $today = now()->toDateString();
        $datenow = now()->toDateTimeString();

        $config = core()->getConfigData();
        $special = false;

        $payment = $this->find($data['code']);
        if (!$payment) {
            return false;
        }

        $member = $this->memberRepository->find($data['member_topup']);
        if (!$member) {
            return false;
        }

        $bank_acc = $this->bankAccountRepository->find($data['account_code']);
        if (!$bank_acc) {
            return false;
        }

        $game = core()->getGame();
        $game_user = $this->gameUserRepository->findOneWhere(['member_code' => $member->code, 'game_code' => $game->code, 'enable' => 'Y']);
        if (!$game_user) {
            $res = $this->gameUserRepository->addGameUser($game->code, $member->code, ['username' => $member->user_name, 'product_id' => 'PGSOFT', 'user_create' => $member->user_name]);
            if ($res['success'] !== true) {
                return false;
            }
            $game_user = $this->gameUserRepository->findOneWhere(['member_code' => $member->code, 'game_code' => $game->code, 'enable' => 'Y']);

        }
        $game_code = $game->code;
        $user_name = $game_user->user_name;
        $user_code = $game_user->code;
        $game_name = $game->name;
        $game_balance = $game_user->balance;
        $member_code = $member->code;
        $amount = $data['value'];

        $selectpro = $this->memberSelectProRepository->findOneWhere(['member_code' => $member_code]);
        if ($selectpro) {
            if ($game_user->balance <= $config->pro_reset) {
                $promotion = $this->promotionRepository->checkSelectPro($selectpro->pro_code, $member_code, $amount, $datenow);
                $bonus = $promotion['bonus'];
                $pro_code = $promotion['pro_code'];
                $pro_name = $promotion['pro_name'];
                $total = $promotion['total'];
                $status_pro = 1;
                $turnpro = $promotion['turnpro'];
                $withdraw_limit = $promotion['withdraw_limit'];
                $withdraw_limit_rate = $promotion['withdraw_limit_rate'];
            } else {
                $bonus = 0;
                $pro_code = 0;
                $pro_name = '';
                $total = $amount;
                $status_pro = $member['status_pro'];
                $turnpro = 0;
                $withdraw_limit = 0;
                $withdraw_limit_rate = 0;
            }
            $selectpro->delete();
        } else {
            $bonus = 0;
            $pro_code = 0;
            $pro_name = '';
            $total = $amount;
            $status_pro = $member['status_pro'];
            $turnpro = 0;
            $withdraw_limit = 0;
            $withdraw_limit_rate = 0;
        }

        if ($bank_acc->bonus > 0) {

            ActivityLoggerUser::activity('Seamless Topup ID : ' . $data['code'], 'พบมี โบนัสเพิ่ม '.$bank_acc->bonus.' ของ บช ที่เติมเข้ามา '.$bank_acc->acc_no, $member->code);

            $now = now(); // ตาม app.timezone

            $isActive = $this->isBetweenDates($bank_acc->start_at,$bank_acc->end_at,$now);
            ActivityLoggerUser::activity('Seamless Topup ID : ' . $data['code'], 'ตรวจสอบ ช่วงเวลา ที่กำหนด ของระบุไว้ ในบช ที่เติมเข้ามา '.$bank_acc->acc_no, $member->code);

            if ($isActive) {
                ActivityLoggerUser::activity('Seamless Topup ID : ' . $data['code'], 'ยังอยู่ใมนช่วงเวลากิจกรรม โบนัสเพิื่ม '.$bank_acc->bonus.' ในบช ที่เติมเข้ามา '.$bank_acc->acc_no, $member->code);

                if ($pro_code === 0) {
                    $bonus = ($amount * $bank_acc->bonus) / 100;
                    if ($bank_acc->bonus_max > 0) {
                        if ($bonus > $bank_acc->bonus_max) {
                            $bonus = $bank_acc->bonus_max;
                        }
                    }

                    ActivityLoggerUser::activity('Seamless Topup ID : ' . $data['code'], 'คำนวนจากยอดฝาก '.$amount.' โบนัสเพิื่ม '.$bank_acc->bonus.'% ได้ โบนัส '.$bonus.' ในบช ที่เติมเข้ามา '.$bank_acc->acc_no, $member->code);

                    $pro_name = "ช่วงเวลา พิเศษ รับยอดเพิ่มขึ้น " . $bank_acc->bonus . "% จากยอดฝาก";
                    $total = ($total + $bonus);
                    $special = true;


                }


            }
        }else{
            ActivityLoggerUser::activity('Seamless Topup ID : ' . $data['code'], 'พบ ไม่มี โบนัสเพิ่ม ของ บช ที่เติมเข้ามา '.$bank_acc->acc_no, $member->code);

        }

        $point = 0;
        $diamond = 0;
        $count_deposit = 1;

        $bank_code = $bank_acc->bank->code;

        $credit_before = $member->balance;
        $credit_after = ($credit_before + $total);

        $chk = $this->allLogRepository->findOneByField('bank_payment_id', $data['code']);
        if ($chk) {
            ActivityLoggerUser::activity('Seamless Topup ID : ' . $data['code'], 'พบรายการเติมเงิน นี้ในระบบแล้ว', $member->code);

            return false;
        }

        try {

            $alllog = $this->allLogRepository->create([
                'before_credit' => $credit_before,
                'after_credit' => $credit_after,
                'status_log' => 0,
                'pro_id' => $pro_code,
                'pro_amount' => $bonus,
                'bonus' => $bonus,
                'game_code' => $game_code,
                'type_record' => 0,
                'gamebalance' => $game_balance,
                'member_code' => $member_code,
                'member_user' => $member['user_name'],
                'amount' => $amount,
                'bank_payment_id' => $data['code'],
                'ip' => $ip,
                'username' => $user_name,
                'remark' => '',
                'user_create' => 'System Auto',
                'user_update' => 'System Auto',
            ]);

        } catch (Throwable $e) {
            ActivityLoggerUser::activity('Seamless Topup ID : ' . $data['code'], 'ไม่สามารถ เพิ่มรายการ all log ได้');
            report($e);

            return false;
        }

        $money_text = 'User ' . $member->user_name . ' Game ID : ' . $user_name . ' จำนวนเงิน ' . $amount . ' โบนัส ' . $bonus . ' จากโปร ' . $pro_name . ' รวมเป็น ' . $total;

        ActivityLoggerUser::activity('Seamless Topup ID : ' . $data['code'], 'เริ่มรายการเติมเงิน ให้กับ User : ' . $member->user_name . ' Game ID : ' . $user_name);
//        ActivityLoggerUser::activity('Seamless Topup ID : '.$data['code'], $money_text);


        DB::beginTransaction();

        try {

            $chknew = $this->memberCreditLogRepository->findOneWhere(['member_code' => $member_code, 'refer_code' => $data['code'], 'refer_table' => 'bank_payment', 'kind' => 'TOPUP']);
            if ($chknew) {
                ActivityLoggerUser::activity('Seamless ฝากเงินเข้าเกม ' . $game_name, $money_text . ' หยุดการทำงาน เนื่องจาก Log ซ้ำ');

                return false;
            }

            $bill = $this->memberCreditLogRepository->create([
                'ip' => $ip,
                'credit_type' => 'D',
                'game_code' => $game_code,
                'gameuser_code' => $user_code,
                'amount' => $amount,
                'bonus' => $bonus,
                'total' => $total,
                'balance_before' => 0,
                'balance_after' => 0,
                'credit' => $amount,
                'credit_bonus' => $bonus,
                'credit_total' => $total,
                'credit_before' => $member->balance,
                'credit_after' => ($member->balance + $total),
                'member_code' => $member_code,
                'user_name' => $member->user_name,
                'pro_code' => $pro_code,
                'pro_name' => $pro_name,
                'bank_code' => $bank_code,
                'refer_code' => $data['code'],
                'refer_table' => 'bank_payment',
                'emp_code' => $data['emp_topup'],
                'auto' => 'Y',
                'remark' => ($payment->emp_topup == 0 ? "(อิงรายการฝากที่ : " . $data['code'] . ') ' : $payment->remark_admin),
                'kind' => 'TOPUP',
                'amount_balance' => $game_user->amount_balance,
                'withdraw_limit' => $game_user->withdraw_limit,
                'withdraw_limit_amount' => $game_user->withdraw_limit_amount,
                'user_create' => 'System Auto',
                'user_update' => 'System Auto',
            ]);

            if ($special) {
                $this->memberCreditLogRepository->create([
                    'ip' => $ip,
                    'credit_type' => 'D',
                    'game_code' => $game_code,
                    'gameuser_code' => $user_code,
                    'amount' => 0,
                    'bonus' => $bonus,
                    'total' => 0,
                    'balance_before' => 0,
                    'balance_after' => 0,
                    'credit' => 0,
                    'credit_bonus' => $bonus,
                    'credit_total' => 0,
                    'credit_before' => 0,
                    'credit_after' => 0,
                    'member_code' => $member_code,
                    'user_name' => $member->user_name,
                    'pro_code' => $pro_code,
                    'pro_name' => $pro_name,
                    'bank_code' => $bank_code,
                    'refer_code' => $data['code'],
                    'refer_table' => 'bank_payment',
                    'emp_code' => $data['emp_topup'],
                    'auto' => 'Y',
                    'remark' => "อ้างอิงรายการฝากที่   ID : " . $data['code']  ." ได้โบนัส จากช่องทางการฝากที่กำหนด เพิ่ม " . $bank_acc->bonus . " %",
                    'kind' => 'G_BONUS',
                    'user_create' => 'System Auto',
                    'user_update' => 'System Auto',
                ]);

            }

            $billcode = 0;

            $alllog->remark = 'Auto Topup and Refer Credit Log ID : ' . $bill->code;
            $alllog->user_update = 'System Auto';
            $alllog->save();

            if ($config->point_open == 'Y') {

                if ($config->point_per_bill == 'N') {

                    if ($amount >= $config->points && $config->points > 0) {
                        $point = floor($amount / $config->points);

                        $this->memberPointLogRepository->create([
                            'point_type' => 'D',
                            'point_amount' => $point,
                            'point_before' => $member->point_deposit,
                            'point_balance' => ($member->point_deposit + $point),
                            'member_code' => $member_code,
                            'remark' => 'ได้รับ Point จากการเติมเงิน ' . $amount . ' บาท เติม ' . $config->points . ' ได้รับ 1 แต้ม สรุปได้รับ ' . $point,
                            'emp_code' => $data['emp_topup'],
                            'ip' => $ip,
                            'user_create' => 'System Auto',
                            'user_update' => 'System Auto',
                        ]);
                    }

                } else {

                    if ($amount >= $config->points_topup && $config->points_topup > 0 && $config->points_amount > 0) {
                        $point = $config->points_amount;

                        $this->memberPointLogRepository->create([
                            'point_type' => 'D',
                            'point_amount' => $point,
                            'point_before' => $member->point_deposit,
                            'point_balance' => ($member->point_deposit + $point),
                            'member_code' => $member_code,
                            'remark' => 'ได้รับ Point จากการเติมเงิน ' . $amount . ' บาท ประเภทนับเป็นบิล เติมยอดมากกว่าหรือเท่ากับ ' . $config->points_topup . ' ได้รับ ' . $point . ' แต้ม',
                            'emp_code' => $data['emp_topup'],
                            'ip' => $ip,
                            'user_create' => 'System Auto',
                            'user_update' => 'System Auto',
                        ]);

                    }

                }

            }

            if ($config->diamond_open == 'Y') {

                if ($config->diamond_per_bill == 'N') {

                    if ($amount >= $config->diamonds && $config->diamonds > 0) {
                        $diamond = floor($amount / $config->diamonds);

                        $this->memberDiamondLogRepository->create([
                            'diamond_type' => 'D',
                            'diamond_amount' => $diamond,
                            'diamond_before' => $member->diamond,
                            'diamond_balance' => ($member->diamond + $diamond),
                            'member_code' => $member_code,
                            'remark' => 'ได้รับเพชร จากการเติมเงิน ' . $amount . ' บาท เติม ' . $config->diamonds . ' ได้รับ 1 เม็ด สรุปได้รับ ' . $diamond,
                            'emp_code' => $data['emp_topup'],
                            'ip' => $ip,
                            'user_create' => 'System Auto',
                            'user_update' => 'System Auto',
                        ]);
                    }

                } else {

                    if ($amount >= $config->diamonds_topup && $config->diamonds_topup > 0 && $config->diamonds_amount > 0) {
                        $diamond = $config->diamonds_amount;

                        $this->memberDiamondLogRepository->create([
                            'diamond_type' => 'D',
                            'diamond_amount' => $diamond,
                            'diamond_before' => $member->diamond,
                            'diamond_balance' => ($member->diamond + $diamond),
                            'member_code' => $member_code,
                            'remark' => 'ได้รับเพชร จากการเติมเงิน ' . $amount . ' บาท ประเภทนับเป็นบิล เติมยอดมากกว่าหรือเท่ากับ ' . $config->diamonds_topup . ' ได้รับ ' . $diamond . ' เม็ด',
                            'emp_code' => $data['emp_topup'],
                            'ip' => $ip,
                            'user_create' => 'System Auto',
                            'user_update' => 'System Auto',
                        ]);

                    }

                }

            }

            $payment->user_id = $user_name;
            $payment->status = 1;
            $payment->before_credit = $member->balance;
            $payment->after_credit = ($member->balance + $total);
            $payment->pro_id = $pro_code;
            $payment->amount = $amount;
            $payment->pro_amount = $bonus;
            $payment->score = $total;
            $payment->date_topup = $datenow;
            $payment->date_approve = $datenow;
            $payment->autocheck = 'Y';
            $payment->remark_admin = $payment->remark_admin . ' (เติมแล้ว)';
            $payment->topup_by = 'System Auto';
            $payment->ip_topup = $ip;
            $payment->save();

            $bills = app('Gametech\Payment\Repositories\BillRepository')->create([
                'complete' => 'Y',
                'enable' => 'Y',
                'refer_code' => $data['code'],
                'refer_table' => 'bank_payment',
                'ref_id' => '',
                'credit_before' => $member->balance,
                'credit_after' => ($member->balance + $total),
                'member_code' => $member_code,
                'game_code' => $game_code,
                'gameuser_code' => $user_code,
                'pro_code' => $pro_code,
                'pro_name' => $pro_name,
                'method' => 'TOPUP',
                'transfer_type' => 1,
                'amount' => $amount,
                'balance_before' => $member->balance,
                'balance_after' => ($member->balance + $total),
                'credit' => $amount,
                'credit_bonus' => $bonus,
                'credit_balance' => $total,
                'amount_request' => ($credit_before + ($total * $turnpro)),
                'amount_limit' => ($credit_before + (($amount + $bonus) * $withdraw_limit_rate)),
                'ip' => $ip,
                'user_create' => $member['name'],
                'user_update' => $member['name'],
            ]);

            if ($pro_code > 0) {

                $this->memberPromotionLogRepository->create([
                    'date_start' => now()->toDateString(),
                    'bill_code' => $bills->code,
                    'member_code' => $member_code,
                    'game_code' => $game_code,
                    'game_name' => $game_name,
                    'gameuser_code' => $user_code,
                    'pro_code' => $pro_code,
                    'pro_name' => $pro_name,
                    'turnpro' => $turnpro,
                    'balance' => ($credit_before - $amount),
                    'amount' => $amount,
                    'bonus' => $bonus,
                    'amount_balance' => ($total * $turnpro),
                    'total_amount_balance' => (($credit_before - $amount) + ($total * $turnpro)),
                    'withdraw_limit' => $withdraw_limit,
                    'withdraw_limit_rate' => 0,
                    'complete' => 'N',
                    'enable' => 'Y',
                    'user_create' => $member['name'],
                    'user_update' => $member['name'],
                ]);

                $billcode = $bills->code;

                $game_user->balance = ($member->balance + $total);
                $game_user->pro_code = $pro_code;
                $game_user->bill_code = $billcode;
                $game_user->turnpro = $turnpro;
                $game_user->amount = $amount;
                $game_user->bonus = $bonus;
                $game_user->amount_balance += ($credit_before + ($total * $turnpro));
                $game_user->withdraw_limit = $withdraw_limit;
                $game_user->withdraw_limit_rate = $withdraw_limit_rate;
                $game_user->withdraw_limit_amount += ($credit_before + (($amount + $bonus) * $withdraw_limit_rate));
                $game_user->save();

            } else {

                if ($game_user->amount_balance > 0 || $game_user->pro_code > 0) {
                    if ($member->balance > $config->pro_reset) {
                        $game_user->amount_balance += ($total * $game_user->turnpro);
                        $game_user->withdraw_limit_amount += ($total * $game_user->withdraw_limit_rate);
                        $game_user->save();
                    } else {
                        $game_user->balance = ($member->balance + $total);
                        $game_user->pro_code = 0;
                        $game_user->bill_code = 0;
                        $game_user->turnpro = 0;
                        $game_user->amount = 0;
                        $game_user->bonus = 0;
                        $game_user->amount_balance = 0;
                        $game_user->withdraw_limit = 0;
                        $game_user->withdraw_limit_rate = 0;
                        $game_user->withdraw_limit_amount = 0;
                        $game_user->save();
                    }
                }

            }

            $bill->amount_balance = $game_user->amount_balance;
            $bill->withdraw_limit = $game_user->withdraw_limit;
            $bill->withdraw_limit_amount = $game_user->withdraw_limit_amount;
            $bill->save();

            $member->sum_deposit += $amount;
            $member->status_pro = $status_pro;
            $member->point_deposit += $point;
            $member->diamond += $diamond;
            $member->balance = ($member->balance + $total);
            $member->count_deposit += $count_deposit;
            $member->save();

            DB::commit();

        } catch (Throwable $e) {
            ActivityLoggerUser::activity('Seamless Topup ID : ' . $data['code'], 'พบปัญหาใน Transaction');
            DB::rollBack();
            ActivityLoggerUser::activity('Seamless Topup ID : ' . $data['code'], 'Rollback Transaction');

            $this->allLogRepository->where('bank_payment_id', $data['code'])->delete();
            report($e);

            return false;

        }

        ActivityLoggerUser::activity('Seamless Topup ID : ' . $data['code'], 'เติมเงินสำเร็จให้กับ User : ' . $member->user_name);
        Notification::send($member, new RealTimeNotification(Lang::get('app.topup.complete') . $total));

        $account = $payment->bank_account;

        $sumToday = app('Gametech\Payment\Repositories\BankPaymentRepository')->income()->active()->complete()->where('account_code', $payment->account_code)->whereDate('date_topup', $today)->sum('value');
        $account->update(['sum_deposit' => $sumToday]);

        if ($account->sum_limit > 0) {
            if ($sumToday >= $account->sum_limit) {
                $account->update(['display_wallet' => 'N']);
                $alt = BankAccount::where('banks', $account->banks)
                    ->where('bank_type', 1)
                    ->where('display_wallet', 'N')
                    ->where('status_auto', 'Y')
                    ->where('enable', 'Y')
                    ->where('sum_deposit', 0)
                    ->orderBy('sort', 'asc')
                    ->first();

                if ($alt) {
                    $alt->update(['display_wallet' => 'Y']);
                }
            }
        }

        return true;

    }

    /**
     * Specify Model class name
     */
    public function model(): string
    {
        return \Gametech\Payment\Models\BankPayment::class;

    }
}
