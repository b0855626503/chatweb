<?php

namespace Gametech\Payment\Repositories;


use Gametech\Core\Eloquent\Repository;
use Gametech\Core\Repositories\AllLogRepository;
use Gametech\LogAdmin\Http\Traits\ActivityLogger;
use Gametech\Member\Repositories\MemberCreditLogRepository;
use Gametech\Member\Repositories\MemberDiamondLogRepository;
use Gametech\Member\Repositories\MemberPointLogRepository;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Promotion\Repositories\PromotionRepository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Throwable;


class BankPaymentRepository extends Repository
{

    use ActivityLogger;

    private $memberRepository;

    private $memberCreditLogRepository;

    private $allLogRepository;

    private $paymentPromotionRepository;

    private $promotionRepository;

    private $bankAccountRepository;

    private $memberPointLogRepository;

    private $memberDiamondLogRepository;

    public function __construct
    (
        MemberRepository $memberRepo,
        MemberCreditLogRepository $memberCreditLogRepo,
        AllLogRepository $allLogRepo,
        PaymentPromotionRepository $paymentPromotionRepo,
        PromotionRepository $promotionRepo,
        BankAccountRepository $bankAccountRepo,
        MemberPointLogRepository $memberPointLogRepo,
        MemberDiamondLogRepository $memberDiamondLogRepo,
        App $app
    )
    {
        $this->memberRepository = $memberRepo;

        $this->memberCreditLogRepository = $memberCreditLogRepo;

        $this->allLogRepository = $allLogRepo;

        $this->paymentPromotionRepository = $paymentPromotionRepo;

        $this->promotionRepository = $promotionRepo;

        $this->bankAccountRepository = $bankAccountRepo;

        $this->memberPointLogRepository = $memberPointLogRepo;

        $this->memberDiamondLogRepository = $memberDiamondLogRepo;

        parent::__construct($app);
    }

    public function loadDeposit($id, $date_start = null, $date_stop = null)
    {
        return $this->with('promotion')->orderBy('date_create', 'desc')->findWhere(['member_topup' => $id, 'enable' => 'Y', ['value', '>', 0]])
            ->when($date_start, function ($query, $date_start) use ($date_stop) {
                return $query->whereRaw("DATE_FORMAT(date_create,'%Y-%m-%d') between ? and ?", [$date_start, $date_stop]);
            });


    }

    public function checkPayment($limit = 5, $bank = 'tw')
    {

        return $this->when($bank, function ($query, $bank) {
            if ($bank == 'tw') {
                return $query->select(['bank_payment.tx_hash', 'bank_payment.*'])->distinct('tx_hash');
            } else {
                return $query->select(['bank_payment.tx_hash', 'bank_payment.*'])->distinct('tx_hash');

            }
        })->orderBy('code', 'asc')
            ->waiting()->active()->income()->where('tx_hash','!=','')
            ->where('bankstatus', 1)
            ->where('autocheck', 'N')
            ->with('bank_account')
            ->whereHas('bank_account', function ($model) use ($bank) {
                $model->active()->topup()->in()->with('bank')->whereHas('bank', function ($model) use ($bank) {
                    $model->where('shortcode', strtoupper($bank));
                });
            })
            ->limit($limit)->get();


    }

    public function loadPayment($limit = 5)
    {

        return $this->scopeQuery(function ($query) use ($limit) {
            return $query->orderBy('code', 'asc')
                ->waiting()->active()->income()
                ->where('bankstatus', 1)
                ->where('autocheck', 'W')
                ->where('member_topup', '<>', 0)
                ->limit($limit);
        })->with(['bank_account' => function ($model) {
            return $model->active()->topup()->in()->with('bank');
        }])->all();

    }


    public function refillPayment($data): bool
    {
        $ip = request()->ip();

        $datenow = now()->toDateTimeString();

        $config = core()->getConfigData();


        $payment = $this->find($data['code']);
        if (!$payment) {
            return false;
        }

        $member = $this->memberRepository->lockForUpdate()->find($data['member_topup']);
        if (!$member) {
            return false;
        }

        $bank_acc = $this->bankAccountRepository->find($data['account_code']);
        if (!$bank_acc) {
            return false;
        }

        $member_code = $member->code;
        $amount = $data['value'];
        $total = $amount;
        $bonus = 0;
        $pro_code = 0;
        $point = 0;
        $diamond = 0;
        $count_deposit = 1;
        $status_pro = $member->status_pro;
        $bank_code = $bank_acc->bank->code;

        $credit_before = $member['balance'];
        $credit_after = ($credit_before + $total);

        $chk = $this->allLogRepository->findOneByField('bank_payment_id', $data['code']);
        if ($chk) {
            ActivityLogger::activitie('Topup ID : ' . $data['code'], 'พบรายการเติมเงิน นี้ในระบบแล้ว');
            return false;
        }

        try {

            $alllog = $this->allLogRepository->create([
                "before_credit" => $credit_before,
                "after_credit" => $credit_after,
                'status_log' => 0,
                "pro_id" => $pro_code,
                "pro_amount" => $bonus,
                "bonus" => $bonus,
                'game_code' => 0,
                'type_record' => 0,
                'gamebalance' => 0,
                "member_code" => $member_code,
                "member_user" => $member['user_name'],
                "amount" => $amount,
                "bank_payment_id" => $data['code'],
                "ip" => $ip,
                "username" => '',
                "remark" => '',
                "user_create" => 'System Auto',
                "user_update" => 'System Auto'
            ]);

        } catch (Throwable $e) {
            ActivityLogger::activitie('Topup ID : ' . $data['code'], 'ไม่สามารถ เพิ่มรายการ all log ได้');
            report($e);
            return false;
        }

        ActivityLogger::activitie('Topup ID : ' . $data['code'], 'เริ่มรายการเติมเงิน ให้กับ User : ' . $member->user_name);
        DB::beginTransaction();

        try {

            $bill = $this->memberCreditLogRepository->create([
                'ip' => $ip,
                'credit_type' => 'D',
                'amount' => $amount,
                'bonus' => $bonus,
                'total' => $total,
                'balance_before' => $credit_before,
                'balance_after' => $credit_after,
                'credit' => 0,
                'credit_bonus' => 0,
                'credit_total' => 0,
                'credit_before' => 0,
                'credit_after' => 0,
                'member_code' => $member_code,
                'pro_code' => $pro_code,
                'bank_code' => $bank_code,
                'refer_code' => $data['code'],
                'refer_table' => 'bank_payment',
                'emp_code' => $data['emp_topup'],
                'auto' => 'Y',
                'remark' => "Auto Topup From Deposit ID : " . $data['code'],
                'kind' => 'TOPUP',
                'user_create' => "System Auto",
                'user_update' => "System Auto"
            ]);


            $alllog->remark = 'Auto Topup and Refer Credit Log ID : ' . $bill->code;
            $alllog->user_update = 'System Auto';
            $alllog->save();


            if ($config->pro_wallet == 'N') {
                $promotion = $this->promotionRepository->CalculatePro($member, $amount, $datenow);
                $bonus = $promotion['bonus'];
                $pro_code = $promotion['pro_code'];
                $total = $promotion['total'];
                $status_pro = $promotion['status_pro'];
            }

            if ($config->point_open == 'Y') {
                if ($amount >= $config->points && $config->points > 0) {
                    $point = floor($amount / $config->points);
                    $this->memberPointLogRepository->create([
                        'point_type' => 'D',
                        'point_amount' => $point,
                        'point_before' => $member->point_deposit,
                        'point_balance' => ($member->point_deposit + $point),
                        'member_code' => $member_code,
                        'remark' => 'เพิ่ม Point จากการเติมเงิน',
                        'emp_code' => $data['emp_topup'],
                        'ip' => $ip,
                        'user_create' => "System Auto",
                        'user_update' => "System Auto",
                    ]);

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
                                'user_create' => "System Auto",
                                'user_update' => "System Auto",
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
                                'user_create' => "System Auto",
                                'user_update' => "System Auto",
                            ]);

                        }

                    }


            }


            $payment->status = 1;
            $payment->before_credit = $credit_before;
            $payment->after_credit = $credit_after;
            $payment->pro_id = $pro_code;
            $payment->amount = $amount;
            $payment->pro_amount = $bonus;
            $payment->score = $total;
            $payment->date_topup = $datenow;
            $payment->date_approve = $datenow;
            $payment->autocheck = 'Y';
            $payment->remark_admin = 'ดำเนินการเติมเข้า User ID สมาชิกแล้ว';
            $payment->topup_by = 'System Auto';
            $payment->ip_topup = $ip;
            $payment->save();


            $member->status_pro = $status_pro;
            $member->balance += $total;
            $member->point_deposit += $point;
            $member->diamond += $diamond;
            $member->count_deposit += $count_deposit;
            $member->save();

            DB::commit();


        } catch (Throwable $e) {
            ActivityLogger::activitie('Topup ID : ' . $data['code'], 'พบปัญหาใน Transaction');
            DB::rollBack();
            ActivityLogger::activitie('Topup ID : ' . $data['code'], 'Rollback Transaction');
            report($e);
            return false;

        }

        ActivityLogger::activitie('Topup ID : ' . $data['code'], 'เติมเงินสำเร็จให้กับ User : ' . $member->user_name);
        return true;

    }


    /**
     * Specify Model class name
     *
     * @return string
     */
    function model(): string
    {
        return 'Gametech\Payment\Contracts\BankPayment';
    }
}
