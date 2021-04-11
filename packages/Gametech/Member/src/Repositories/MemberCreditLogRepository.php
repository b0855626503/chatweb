<?php

namespace Gametech\Member\Repositories;

use Exception;
use Gametech\Core\Eloquent\Repository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class MemberCreditLogRepository extends Repository
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
        return 'Gametech\Member\Contracts\MemberCreditLog';
    }

    public function setWallet(array $data)
    {

        $ip = request()->ip();
        $credit_balance = 0;
        $member_code = $data['member_code'];
        $amount = $data['amount'];
        $method = $data['method'];
        $kind = $data['kind'];
        $remark = $data['remark'];
        $emp_code = $data['emp_code'];
        $emp_name = $data['emp_name'];

        DB::beginTransaction();
        try {
            Event::dispatch('customer.set.wallet.before', $data);

            $member = $this->memberRepository->sharedLock()->find($member_code);

            if($method == 'D'){
                $credit_balance = ($member->balance + $amount);
            }elseif($method == 'W'){
                $credit_balance = ($member->balance - $amount);
                if($credit_balance < 0){
                    return false;
                }
            }


            $bill = $this->create([
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
                'kind' => $kind,
                'auto' => 'N',
                'remark' => $remark,
                'emp_code' => $emp_code,
                'ip' => $ip,
                'user_create' => $emp_name,
                'user_update' => $emp_name
            ]);

            $member->balance = $credit_balance;
            $member->save();

            Event::dispatch('customer.set.wallet.after', $bill);

        } catch (Exception $e) {
            DB::rollBack();

            return false;
        }

        DB::commit();

        return true;
    }
}
