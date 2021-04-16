<?php

namespace Gametech\Member\Repositories;

use Gametech\Core\Eloquent\Repository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Throwable;

class MemberPointLogRepository extends Repository
{
    private $memberRepository;

    public function __construct
    (
        MemberRepository $memberRepo,
        App $app
    )
    {
        $this->memberRepository = $memberRepo;
        parent::__construct($app);
    }

    /**
     * Specify Model class name
     *
     * @return string
     */
    function model(): string
    {
        return 'Gametech\Member\Contracts\MemberPointLog';
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

        $member = $this->memberRepository->findOrFail($member_code);

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

            $this->create([
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

            DB::commit();

        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            return false;
        }

        return true;
    }
}
