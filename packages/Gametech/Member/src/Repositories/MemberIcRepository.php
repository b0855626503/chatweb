<?php

namespace Gametech\Member\Repositories;

use Exception;
use Gametech\Core\Eloquent\Repository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class MemberIcRepository extends Repository
{
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
     * @return mixed
     */
    function model()
    {
        return 'Gametech\Member\Contracts\MemberIc';
    }

    public function refill(array $data)
    {

        $member_code = $data['upline_code'];
        $downline_code = $data['member_code'];
        $amount = $data['balance'];
        $cashback = $data['ic'];
        $date_cashback = $data['date_cashback'];
        $ip = $data['ip'];
        $emp_code = $data['emp_code'];
        $emp_name = $data['emp_name'];

        $chk = $this->findOneWhere(['date_cashback' => $date_cashback , 'member_code' => $member_code , 'downline_code' => $downline_code]);
        if($chk){
            if($chk->topupic == 'Y' || $chk->topupic == 'X'){
                return false;
            }
        }

        DB::beginTransaction();
        try {
            Event::dispatch('customer.ic.before', $data);

            $member = $this->memberRepository->sharedLock()->find($member_code);

            $total = ($member->balance_free + $cashback);

            if($chk){
                $bill = $this->update([
                    'member_code' => $member_code,
                    'downline_code' => $downline_code,
                    'date_cashback' => $date_cashback,
                    'balance' => $amount,
                    'ic' => $cashback,
                    'amount' => $cashback,
                    'topupic' => 'Y',
                    'ip_admin' => $ip,
                    'emp_code' => $emp_code,
                    'date_approve' => now()->toDateTimeString(),
                    'user_create' => $emp_name,
                    'user_update' => $emp_name
                ],$chk->code);

                if($bill->wasChanged()){
                    $bill->code = $chk->code;
                }
            }else{
                $bill = $this->create([
                    'member_code' => $member_code,
                    'downline_code' => $downline_code,
                    'date_cashback' => $date_cashback,
                    'balance' => $amount,
                    'ic' => $cashback,
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
                'member_code' => $member_code,
                'kind' => 'IC',
                'remark' => "เติม IC อ้างอิง record : ".$bill->code,
                'emp_code' => $emp_code,
                'user_create' => $emp_name,
                'user_update' => $emp_name,
            ]);

            $member->balance_free = $total;
            $member->save();

            Event::dispatch('customer.ic.after', $bill);

        } catch (Exception $e) {
            DB::rollBack();
            report($e);
            return false;
        }

        DB::commit();

        return true;
    }
}
