<?php

namespace Gametech\Member\Repositories;

use Gametech\Core\Eloquent\Repository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Throwable;

class MemberPointLogRepository extends Repository
{
    private $memberRepository;

    private $memberCreditLogRepository;

    public function __construct
    (
        MemberRepository $memberRepo,
        MemberCreditLogRepository $memberCreditLogRepo,
        App $app
    )
    {
        $this->memberRepository = $memberRepo;

        $this->memberCreditLogRepository = $memberCreditLogRepo;

        parent::__construct($app);
    }

    /**
     * Specify Model class name
     *
     * @return string
     */
    function model(): string
    {
        return \Gametech\Member\Models\MemberPointLog::class;

    }

    public function setPoint(array $data): bool
    {

        $ip = request()->ip();
        $credit_balance = 0;
        $member_code = $data['member_code'];
        $amount = $data['amount'];
        $method = $data['method'];
        $remark = $data['remark'];
        $emp_code = $data['emp_code'];
        $emp_name = $data['emp_name'];

        $member = $this->memberRepository->find($member_code);

        if ($method == 'D') {
            $credit_balance = ($member->point_deposit + $amount);
        } elseif ($method == 'W') {
            $credit_balance = ($member->point_deposit - $amount);
            if ($credit_balance < 0) {
                return false;
            }
        }

        DB::beginTransaction();
        try {

            $bill = $this->create([
                'point_type' => $method,
                'point_amount' => $amount,
                'point_before' => $member->point_deposit,
                'point_balance' => $credit_balance,
                'member_code' => $member_code,
                'remark' => $remark,
                'emp_code' => $emp_code,
                'ip' => $ip,
                'user_create' => $emp_name,
                'user_update' => $emp_name
            ]);

            $member->point_deposit = $credit_balance;
            $member->save();

            $this->memberCreditLogRepository->create([
                'refer_code' => $bill->code,
                'refer_table' => 'members_point_log',
                'credit_type' => $method,
                'amount' => $amount,
                'bonus' => 0,
                'total' => $amount,
                'balance_before' => $member->balance,
                'balance_after' => $credit_balance,
                'credit' => 0,
                'credit_bonus' => 0,
                'credit_total' => 0,
                'credit_before' => 0,
                'credit_after' => 0,
                'member_code' => $member_code,
                'user_name' => $member->user_name,
                'kind' => 'SETPOINT',
                'auto' => 'N',
                'remark' => $remark,
                'emp_code' => $emp_code,
                'ip' => $ip,
                'user_create' => $emp_name,
                'user_update' => $emp_name
            ]);

            DB::commit();

        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            return false;
        }

        return true;
    }
}
