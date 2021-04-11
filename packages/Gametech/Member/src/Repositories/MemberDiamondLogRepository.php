<?php

namespace Gametech\Member\Repositories;

use Exception;
use Gametech\Core\Eloquent\Repository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

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
     * @return mixed
     */
    function model()
    {
        return 'Gametech\Member\Contracts\MemberDiamondLog';
    }

    public function setDiamond(array $data)
    {

        $ip = request()->ip();
        $credit_balance = 0;
        $member_code = $data['member_code'];
        $amount = $data['amount'];
        $method = $data['method'];
        $remark = $data['remark'];
        $emp_code = $data['emp_code'];
        $emp_name = $data['emp_name'];

        DB::beginTransaction();
        try {
            Event::dispatch('customer.set.diamond.before', $data);

            $member = $this->memberRepository->sharedLock()->find($member_code);

            if($method == 'D'){
                $credit_balance = ($member->diamond + $amount);
            }elseif($method == 'W'){
                $credit_balance = ($member->diamond - $amount);
                if($credit_balance < 0){
                    return false;
                }
            }


            $bill = $this->create([
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

            Event::dispatch('customer.set.diamond.after', $bill);

        } catch (Exception $e) {
            DB::rollBack();

            return false;
        }

        DB::commit();

        return true;
    }
}
