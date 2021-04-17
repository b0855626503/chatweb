<?php

namespace Gametech\Member\Repositories;

use Gametech\Core\Eloquent\Repository;
use Gametech\LogAdmin\Http\Traits\ActivityLogger;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Throwable;

class MemberCashbackRepository extends Repository
{
    use ActivityLogger;

    private $memberRepository;

    private $memberFreeCreditRepository;

    public function __construct
    (
        MemberRepository $memberRepo,
        MemberFreeCreditRepository $memberFreeCreditRepo,
        App $app
    )
    {
        $this->memberRepository = $memberRepo;
        $this->memberFreeCreditRepository = $memberFreeCreditRepo;
        parent::__construct($app);
    }

    /**
     * Specify Model class name
     *
     * @return string
     */
    function model(): string
    {
        return 'Gametech\Member\Contracts\MemberCashback';
    }

    public function refill(array $data): bool
    {

        $member_code = $data['upline_code'];
        $downline_code = $data['member_code'];
        $amount = $data['balance'];
        $cashback = $data['cashback'];
        $date_cashback = $data['date_cashback'];
        $ip = $data['ip'];
        $emp_code = $data['emp_code'];
        $emp_name = $data['emp_name'];

        $chk = $this->findOneWhere(['date_cashback' => $date_cashback, 'downline_code' => $downline_code]);
        if ($chk) {
            if ($chk->topupic == 'Y' || $chk->topupic == 'X') {
                return false;
            }
        }

        $member = $this->memberRepository->find($downline_code);

        $total = ($member->balance_free + $cashback);

        ActivityLogger::activitie('CASHBACK REFER USER : ' . $member->user_name, 'เริ่มรายการ CASHBACK');


        DB::beginTransaction();
        try {


            if ($chk) {
                $bill = $this->update([
                    'member_code' => $member_code,
                    'downline_code' => $downline_code,
                    'date_cashback' => $date_cashback,
                    'balance' => $amount,
                    'cashback' => $cashback,
                    'amount' => $cashback,
                    'topupic' => 'Y',
                    'ip_admin' => $ip,
                    'emp_code' => $emp_code,
                    'date_approve' => now()->toDateTimeString(),
                    'user_create' => $emp_name,
                    'user_update' => $emp_name
                ], $chk->code);

                if ($bill->wasChanged()) {
                    $bill->code = $chk->code;
                }

            } else {
                $bill = $this->create([
                    'member_code' => $member_code,
                    'downline_code' => $downline_code,
                    'date_cashback' => $date_cashback,
                    'balance' => $amount,
                    'cashback' => $cashback,
                    'amount' => $cashback,
                    'topupic' => 'Y',
                    'ip_admin' => $ip,
                    'emp_code' => $emp_code,
                    'date_approve' => now()->toDateTimeString(),
                    'user_create' => $emp_name,
                    'user_update' => $emp_name
                ]);
            }

            $this->memberFreeCreditRepository->create([
                'ip' => $ip,
                'credit_type' => 'D',
                'credit' => $cashback,
                'credit_amount' => $cashback,
                'credit_before' => $member->balance_free,
                'credit_balance' => $total,
                'member_code' => $downline_code,
                'kind' => 'CASHBACK',
                'remark' => "เติม Cashback อ้างอิง record : " . $bill->code,
                'emp_code' => $emp_code,
                'user_create' => $emp_name,
                'user_update' => $emp_name,
            ]);

            $member->balance_free = $total;
            $member->save();
            DB::commit();


        } catch (Throwable $e) {
            DB::rollBack();
            ActivityLogger::activitie('CASHBACK REFER USER : ' . $member->user_name, 'พบข้อผิดพลาด CASHBACK');

            report($e);
            return false;
        }

        ActivityLogger::activitie('CASHBACK REFER USER : ' . $member->user_name, 'ทำรายการ CASHBACK สำเร็จ');

        return true;
    }
}
