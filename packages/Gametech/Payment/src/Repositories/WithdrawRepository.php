<?php

namespace Gametech\Payment\Repositories;

use Gametech\Core\Eloquent\Repository;
use Gametech\LogUser\Http\Traits\ActivityLoggerUser;
use Gametech\Member\Repositories\MemberCreditLogRepository;
use Gametech\Member\Repositories\MemberLogRepository;
use Gametech\Member\Repositories\MemberRepository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Throwable;

class WithdrawRepository extends Repository
{
    private $memberRepository;

    private $memberLogRepository;

    private $memberCreditLogRepository;

    /**
     * WithdrawRepository constructor.
     * @param MemberLogRepository $memberLogRepository
     * @param MemberRepository $memberRepository
     * @param MemberCreditLogRepository $memberCreditLogRepo
     * @param App $app
     */
    public function __construct
    (
        MemberLogRepository $memberLogRepository,
        MemberRepository $memberRepository,
        MemberCreditLogRepository $memberCreditLogRepo,
        App $app
    )
    {
        $this->memberLogRepository = $memberLogRepository;

        $this->memberRepository = $memberRepository;

        $this->memberCreditLogRepository = $memberCreditLogRepo;

        parent::__construct($app);
    }

    /**
     * @param $id
     * @param $amount
     * @return bool
     */

    public function withdraw($id, $amount): bool
    {

        $datenow = now();
        $timenow = $datenow->toTimeString();
        $today = $datenow->toDateString();
        $ip = request()->ip();

        $member = $this->memberRepository->find($id);

        if ($member->balance < $amount) {
            ActivityLoggerUser::activity('Request Withdraw Wallet User : ' . $member->user_name, 'ยอดแจ้งถอน มากกว่า ยอดที่มี');
            return false;
        }

        $oldcredit = $member->balance;
        $aftercredit = ($member->balance - $amount);


        ActivityLoggerUser::activity('Request Withdraw Wallet User : ' . $member->user_name, 'เริ่มต้นทำรายการแจ้งถอน');

        DB::beginTransaction();

        try {


            $data = [
                'member_code' => $id,
                'amount' => $amount,
                'oldcredit' => $oldcredit,
                'aftercredit' => $aftercredit,
                'ip' => $ip
            ];


            $this->memberLogRepository->create([
                'member_code' => $member->code,
                'mode' => 'WITHDRAW',
                'menu' => 'withdraw',
                'record' => $member->code,
                'remark' => 'ถอนเงินจาก กระเป๋า Wallet',
                'item_before' => serialize($member),
                'item' => serialize($data),
                'ip' => $ip,
                'user_create' => $member->name
            ]);

            $bill = $this->create([
                'member_code' => $member->code,
                'member_user' => $member->user_name,
                'bankm_code' => $member->bank_code,
                'amount' => $amount,
                'oldcredit' => $oldcredit,
                'aftercredit' => $aftercredit,
                'status' => 0,
                'date_record' => $today,
                'bankout' => '',
                'remark' => '',
                'timedept' => $timenow,
                'ip' => $ip,
                'user_create' => $member->name,
                'user_update' => $member->name
            ]);

            $this->memberCreditLogRepository->create([
                'ip' => $ip,
                'credit_type' => 'W',
                'amount' => $amount,
                'bonus' => 0,
                'total' => $amount,
                'balance_before' => $oldcredit,
                'balance_after' => $aftercredit,
                'credit' => 0,
                'credit_bonus' => 0,
                'credit_total' => 0,
                'credit_before' => 0,
                'credit_after' => 0,
                'member_code' => $member->code,
                'game_code' => 0,
                'bank_code' => $member->bank_code,
                'gameuser_code' => 0,
                'auto' => 'N',
                'remark' => "ทำรายการถอนเงิน อ้างอิงบิล ID :" . $bill->code,
                'kind' => 'WITHDRAW',
                'user_create' => $member['name'],
                'user_update' => $member['name']
            ]);

            $member->balance = $aftercredit;
            $member->ip = $ip;
            $member->save();

            DB::commit();

        } catch (Throwable $e) {
            ActivityLoggerUser::activity('Request Withdraw Wallet User : ' . $member->user_name, 'พบปัญหาในการทำรายการ');
            DB::rollBack();
            ActivityLoggerUser::activity('Request Withdraw Wallet User : ' . $member->user_name, 'ดำเนินการ Rollback แล้ว');

            report($e);
            return false;
        }


        ActivityLoggerUser::activity('Request Withdraw Wallet User : ' . $member->user_name, 'ทำรายการแจ้งถอนสำเร็จแล้ว');
        return true;

    }


    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model(): string
    {
        return 'Gametech\Payment\Contracts\Withdraw';
    }
}
