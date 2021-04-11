<?php

namespace Gametech\Payment\Repositories;

use Gametech\Core\Eloquent\Repository;
use Gametech\Member\Repositories\MemberCreditLogRepository;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Promotion\Repositories\PromotionRepository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Throwable;

class PaymentPromotionRepository extends Repository
{
    private $memberRepository;

    private $memberCreditLogRepository;

    private $promotionRepository;

    public function __construct
    (
        MemberRepository $memberRepo,
        MemberCreditLogRepository $memberCreditLogRepo,
        PromotionRepository $promotionRepo,
        App $app
    )
    {
        $this->memberRepository = $memberRepo;

        $this->memberCreditLogRepository = $memberCreditLogRepo;

        $this->promotionRepository = $promotionRepo;

        parent::__construct($app);
    }

    public function checkFastStart($amount, $user_topup_code, $payment_code = 0): bool
    {
        $datenow = now()->toDateTimeString();
        $ip = request()->ip();

        $chk = $this->promotionRepository->findOneByField('id', 'pro_faststart');
        if ($chk->enable == 'N' || $chk->use_wallet == 'N' || $chk->active == 'N' || $chk->use_auto == 'N') {
            return false;
        }

        $user_topup = $this->memberRepository->find($user_topup_code);

        $upline_code = $user_topup->upline_code;
        $downline_code = $user_topup_code;

        if ($upline_code > 0) {
            $cnt = $this->findWhere(['member_code' => $upline_code, 'downline_code' => $downline_code, 'pro_code' => $chk->code]);
            if ($cnt->count() == 0) {
                $promotion = $this->promotionRepository->checkPromotionId("pro_faststart", $amount, $datenow);
                $bonus = $promotion['bonus'];
                $total = $promotion['total'];
                if ($bonus > 0) {

                    DB::beginTransaction();
                    try {

                        $member = $this->memberRepository->find($upline_code);

                        $credit_before = $member['balance'];
                        $credit_after = ($credit_before + $bonus);

                        $this->create([
                            'ip' => $ip,
                            'pro_code' => '6',
                            'amount' => $amount,
                            'credit' => $amount,
                            'credit_bonus' => $bonus,
                            'credit_before' => $credit_before,
                            'credit_after' => $credit_after,
                            'credit_balance' => $total,
                            'member_code' => $upline_code,
                            'downline_code' => $downline_code,
                            'remark' => 'Refer From User : ' . $user_topup->user_name . ' Deposit ID : ' . $payment_code . ' ' . $promotion['type'],
                            'user_create' => "System Auto",
                            'user_update' => "System Auto"
                        ]);

                        $this->memberCreditLogRepository->create([

                            'ip' => $ip,
                            'credit_type' => 'D',
                            'amount' => $amount,
                            'bonus' => $bonus,
                            'total' => $bonus,
                            'balance_before' => $credit_before,
                            'balance_after' => $credit_after,
                            'credit' => 0,
                            'credit_bonus' => 0,
                            'credit_total' => 0,
                            'credit_before' => 0,
                            'credit_after' => 0,
                            'member_code' => $upline_code,
                            'pro_code' => 6,
                            'auto' => 'Y',
                            'remark' => 'Refer From User : ' . $user_topup->user_name . ' Deposit ID : ' . $payment_code . ' ' . $promotion['type'],
                            'kind' => 'FASTSTART',
                            'user_create' => "System Auto",
                            'user_update' => "System Auto"
                        ]);

                        $member->balance = $credit_after;
                        $member->save();
                        DB::commit();

                    } catch (Throwable $e) {

                        DB::rollBack();
                        report($e);
                        return false;
                    }
                }
            }
        }

        return true;

//        if ($upline_code > 0) {
//            $datenow = now()->toDateTimeString();
//            $cnt = $this->where('member_code', $upline_code)->where('downline_code', $downline_code)->where('pro_code', 6)->count();
//            if ($cnt == 0) {
//                $promotion = $this->promotionRepository->checkPromotionId("pro_faststart", $amount, now()->toDateTimeString());
//                $bonus = $promotion['bonus'];
//                $total = $promotion['total'];
//                if ($bonus > 0) {
//
//                    DB::beginTransaction();
//                    try {
//
//                        $member = $this->memberRepository->find($upline_code);
//
//                        $credit_before = $member['balance'];
//                        $credit_after = ($credit_before + $bonus);
//
//                        $this->create([
//                            'ip' => request()->ip(),
//                            'pro_code' => '6',
//                            'amount' => $amount,
//                            'credit' => $amount,
//                            'credit_bonus' => $bonus,
//                            'credit_before' => $credit_before,
//                            'credit_after' => $credit_after,
//                            'credit_balance' => $total,
//                            'member_code' => $upline_code,
//                            'downline_code' => $downline_code,
//                            'remark' => $promotion['type'],
//                            'user_create' => "System Auto",
//                            'user_update' => "System Auto",
//                            'date_create' => $datenow,
//                            'date_update' => $datenow,
//                        ]);
//
//                        $member->balance = $credit_after;
//                        $member->save();
//
//                    } catch (Exception $e) {
//                        report($e);
//                        DB::rollBack();
//                        return false;
//                    }
//
//                    DB::commit();
//                    return true;
//                }
//
//            }
//        }
    }

    /**
     * Specify Model class name
     *
     * @return string
     */
    function model(): string
    {
        return 'Gametech\Payment\Contracts\PaymentPromotion';
    }
}
