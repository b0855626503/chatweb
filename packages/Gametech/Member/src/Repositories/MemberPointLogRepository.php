<?php

namespace Gametech\Member\Repositories;

use Exception;
use Gametech\Core\Eloquent\Repository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

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
     * @return mixed
     */
    function model()
    {
        return 'Gametech\Member\Contracts\MemberPointLog';
    }

    public function setPoint(array $data)
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
            Event::dispatch('customer.set.point.before', $data);

            $member = $this->memberRepository->sharedLock()->find($member_code);

            if($method == 'D'){
                $credit_balance = ($member->point_deposit + $amount);
            }elseif($method == 'W'){
                $credit_balance = ($member->point_deposit - $amount);
                if($credit_balance < 0){
                    return false;
                }
            }


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

            Event::dispatch('customer.set.point.after', $bill);

        } catch (Exception $e) {
            DB::rollBack();

            return false;
        }

        DB::commit();

        return true;
    }
}
