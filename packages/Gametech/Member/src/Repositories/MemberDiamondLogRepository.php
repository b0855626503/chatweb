<?php

namespace Gametech\Member\Repositories;

use Gametech\Core\Eloquent\Repository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Throwable;

class MemberDiamondLogRepository extends Repository
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
        return 'Gametech\Member\Contracts\MemberDiamondLog';
    }

    public function setDiamond(array $data): bool
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
            $credit_balance = ($member->diamond + $amount);
        } elseif ($method == 'W') {
            $credit_balance = ($member->diamond - $amount);
            if ($credit_balance < 0) {
                return false;
            }
        }

        DB::beginTransaction();

        try {

            $this->create([
                'diamond_type' => $method,
                'diamond_amount' => $amount,
                'diamond_before' => $member->diamond,
                'diamond_balance' => $credit_balance,
                'member_code' => $member_code,
                'remark' => $remark,
                'emp_code' => $emp_code,
                'ip' => $ip,
                'user_create' => $emp_name,
                'user_update' => $emp_name
            ]);

            $member->diamond = $credit_balance;
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
