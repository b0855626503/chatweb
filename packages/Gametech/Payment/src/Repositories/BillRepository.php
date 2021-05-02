<?php

namespace Gametech\Payment\Repositories;


use Gametech\Core\Eloquent\Repository;
use Gametech\Game\Repositories\GameUserRepository;
use Gametech\LogAdmin\Http\Traits\ActivityLogger;
use Gametech\LogUser\Http\Traits\ActivityLoggerUser;
use Gametech\Member\Repositories\MemberCreditLogRepository;
use Gametech\Member\Repositories\MemberLogRepository;
use Gametech\Member\Repositories\MemberPromotionLogRepository;
use Gametech\Member\Repositories\MemberRepository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Throwable;


class BillRepository extends Repository
{
    use ActivityLoggerUser, ActivityLogger;


    private $gameUserRepository;

    private $memberRepository;

    private $paymentLogRepository;

    private $memberLogRepository;

    private $memberCreditLogRepository;

    private $memberPromotionLogRepository;

    private $paymentWaitingRepository;

    /**
     * BillRepository constructor.
     * @param GameUserRepository $gameUserRepo
     * @param MemberRepository $memberRepo
     * @param PaymentLogRepository $paymentLogRepo
     * @param MemberLogRepository $memberLogRepo
     * @param MemberCreditLogRepository $memberCreditLogRepo
     * @param MemberPromotionLogRepository $memberPromotionLogRepo
     * @param PaymentWaitingRepository $paymentWaitingRepo
     * @param App $app
     */
    public function __construct
    (
        GameUserRepository $gameUserRepo,
        MemberRepository $memberRepo,
        PaymentLogRepository $paymentLogRepo,
        MemberLogRepository $memberLogRepo,
        MemberCreditLogRepository $memberCreditLogRepo,
        MemberPromotionLogRepository $memberPromotionLogRepo,
        PaymentWaitingRepository $paymentWaitingRepo,
        App $app
    )
    {

        $this->gameUserRepository = $gameUserRepo;

        $this->memberRepository = $memberRepo;

        $this->memberLogRepository = $memberLogRepo;

        $this->paymentLogRepository = $paymentLogRepo;

        $this->memberCreditLogRepository = $memberCreditLogRepo;

        $this->memberPromotionLogRepository = $memberPromotionLogRepo;

        $this->paymentWaitingRepository = $paymentWaitingRepo;

        parent::__construct($app);
    }


    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model(): string
    {
        return 'Gametech\Payment\Contracts\Bill';
    }

    public function transferWallet(array $data): array
    {
        $return['success'] = false;

        $ip = request()->ip();

        $member_code = $data['member_code'];
        $game_code = $data['game_code'];
        $game_name = $data['game_name'];
        $user_code = $data['user_code'];
        $user_name = $data['user_name'];
        $pro_code = $data['pro_code'];
        $amount = $data['amount'];
        $bonus = $data['bonus'];
        $total = $amount;

        $game_balance = $data['game_balance'];

        $user = $this->gameUserRepository->find($user_code);
        if ($user->balance != $game_balance) {
            ActivityLoggerUser::activity('Transfer Game '.$game_name.' To Wallet', 'จำนวนเงิน '.$total.' พบปัญหายอดเงินในการทำรายการไม่ถูกต้อง');
            $return['msg'] = 'ยอดเงินที่ทำรายการ ไม่ถูกต้อง โปรดทำรายการใหม่ อีกครั้งในภายหลัง';
            return $return;
        }

        if ($amount < $user->amount_balance) {
            ActivityLoggerUser::activity('Transfer Game '.$game_name.' To Wallet', 'จำนวนเงิน '.$total.' ไม่สามารถทำรายการได้เนื่องจากติดยอดเทิน');
            $return['msg'] = 'ไม่สามารถทำรายการได้ เนื่องจากยังไม่ผ่านเงื่อนไข โปรโมชั่น';
            return $return;
        }

        if ($amount > $user->balance) {
            ActivityLoggerUser::activity('Transfer Game '.$game_name.' To Wallet', 'จำนวนเงิน '.$total.' ไม่สามารถทำรายการได้เนื่องจาก ยอดเงินไม่ถูกต้อง');
            $return['msg'] = 'ไม่สามารถทำรายการได้ เนื่องจาก ยอดเงินไม่ถูกต้อง';
            return $return;
        }

        $withdraw_limit = $data['withdraw_limit'];
        if ($withdraw_limit > 0) {
            ActivityLoggerUser::activity('Transfer Game '.$game_name.' To Wallet', 'จำนวนเงิน '.$total.' เกมมีการจำกัดยอดเงินที่ได้รับจริง');
            $amount = $withdraw_limit;

            if(floor($total) != floor($user->balance)){
                ActivityLoggerUser::activity('Transfer Game '.$game_name.' To Wallet', 'จำนวนเงิน '.$total.' ยอดแจ้งถอน ไม่เท่ากับยอดเงินในเกม');
                $return['msg'] = 'ไม่สามารถทำรายการได้ เนื่องจาก ต้องโยกออกทั้งหมดตามเงื่อนไขโปรโมชั่น โปรดใส่จำนวนเต็มในการโยก สามารถเหลือเศษได้';
                return $return;
            }
        }



        $balance_before = $data['member_balance'];
        $balance_after = ($balance_before + $amount);


        ActivityLoggerUser::activity('Transfer Game '.$game_name.' To Wallet', 'จำนวนเงิน '.$total.' เริ่มต้นทำรายการโยกเงิน');

        $response = $this->gameUserRepository->UserWithdraw($game_code, $user_name, $total, false);
        if ($response['success'] !== true) {
            $return['msg'] = 'ไม่สามารถ ทำรายการโยกเงินออกจากเกมได้';
            ActivityLoggerUser::activity('Transfer Game '.$game_name.' To Wallet', 'จำนวนเงิน '.$total.' ไม่สามารถถอนเงินออกจากเกมได้');
            return $return;
        } else {
            ActivityLoggerUser::activity('Transfer Game '.$game_name.' To Wallet', 'จำนวนเงิน '.$total.' ระบบทำการถอนเงินออกจากเกมแล้ว');
        }

        DB::beginTransaction();

        try {

            $member = $this->memberRepository->find($member_code);

            $bill = $this->create([
                'enable' => 'Y',
                'ref_id' => $response['ref_id'],
                'credit_before' => $response['before'],
                'credit_after' => $response['after'],
                'member_code' => $member_code,
                'game_code' => $game_code,
                'pro_code' => $pro_code,
                'transfer_type' => 2,
                'amount_request' => $total,
                'amount_limit' => $withdraw_limit,
                'amount' => $amount,
                'balance_before' => $balance_before,
                'balance_after' => $balance_after,
                'credit' => $amount,
                'credit_bonus' => $bonus,
                'credit_balance' => $total,
                'ip' => $ip,
                'user_create' => $member['name'],
                'user_update' => $member['name']
            ]);


            $this->paymentLogRepository->create([
                'msg' => 'โยกเงินออกจากเกม เข้า Wallet เรียบร้อย',
                'status' => 'COMPLETE',
                'showmsg' => 'Y',
                'confirm' => 'Y',
                'enable' => 'Y',
                'bill_code' => $bill->code,
                'member_code' => $member_code,
                'game_code' => $game_code,
                'token' => '',
                'transfer_type' => 2,
                'amount' => $amount,
                'ip' => $ip,
                'user_create' => $member['name'],
                'user_update' => $member['name']
            ]);

            $this->memberLogRepository->create([
                'member_code' => $member_code,
                'mode' => 'TRANSFER_OUT',
                'menu' => 'transferwallet',
                'record' => $member_code,
                'remark' => 'โยกเงินออกจากเกม เข้า Wallet',
                'item_before' => serialize($data),
                'item' => serialize($member),
                'ip' => $ip,
                'user_create' => $member['name']
            ]);

            $this->memberCreditLogRepository->create([

                'ip' => $ip,
                'credit_type' => 'D',
                'amount' => $amount,
                'bonus' => $bonus,
                'total' => $total,
                'balance_before' => $balance_before,
                'balance_after' => $balance_after,
                'credit' => $total,
                'credit_bonus' => 0,
                'credit_total' => $total,
                'credit_before' => $response['before'],
                'credit_after' => $response['after'],
                'member_code' => $member_code,
                'game_code' => $game_code,
                'gameuser_code' => $user_code,
                'pro_code' => $pro_code,
                'bank_code' => 0,
                'refer_code' => $bill->code,
                'refer_table' => 'bills',
                'auto' => 'N',
                'remark' => "โยกเงินออกจากเกมเข้า Wallet อ้างอิงบิล ID :" . $bill->code,
                'kind' => 'TRANSFER',
                'user_create' => $member['name'],
                'user_update' => $member['name']
            ]);


            $this->gameUserRepository->update([
                'balance' => $response['after'],
                'pro_code' => 0,
                'bill_code' => $bill->code,
                'turnpro' => 0,
                'amount' => 0,
                'bonus' => 0,
                'amount_balance' => 0,
                'withdraw_limit' => 0
            ], $user_code);

            $member->balance = ($member->balance + $amount);
            $member->save();

            DB::commit();


        } catch (Throwable $e) {
            ActivityLoggerUser::activity('Confirm Transfer Game '.$game_name.' To Wallet', 'จำนวนเงิน '.$total.' พบปัญหาในการทำรายการ');
            DB::rollBack();
            ActivityLoggerUser::activity('Reject Transfer Game '.$game_name.' To Wallet', 'จำนวนเงิน '.$total.' ดำเนินการ Rollback การทำรายการแล้ว');

            $response = $this->gameUserRepository->UserDeposit($game_code, $user_name, $total);
            if ($response['success'] === true) {
                ActivityLoggerUser::activity('Transfer Game '.$game_name.' To Wallet', 'จำนวนเงิน '.$total.' ระบบทำการคืนยอดเงินเข้าเกมแล้ว');
            } else {
                ActivityLoggerUser::activity('Transfer Game '.$game_name.' To Wallet', 'จำนวนเงิน '.$total.' ระบบไม่สามารถคืนยอดเงินเข้าเกม');
            }


            $return['msg'] = 'ไม่สามารถทำรายการได้ โปรดลองใหม่อีกครั้ง';
            report($e);
            return $return;
        }

        ActivityLoggerUser::activity('Transfer Game '.$game_name.' To Wallet', 'จำนวนเงิน '.$total.' ทำรายการโยกเงินสำเร็จ');
        $return['success'] = true;
        $return['data'] = $bill;
        return $return;


    }

    public function transferGame(array $data): array
    {
        $return['success'] = false;

        $ip = request()->ip();

        $member_code = $data['member_code'];
        $game_code = $data['game_code'];
        $game_name = $data['game_name'];
        $user_code = $data['user_code'];
        $user_name = $data['user_name'];
        $pro_code = $data['pro_code'];
        $pro_name = $data['pro_name'];
        $turnpro = $data['turnpro'];
        $withdraw_limit = $data['withdraw_limit'];
        $amount = $data['amount'];
        $bonus = $data['bonus'];
        $total = $data['total'];
        $balance_before = $data['member_balance'];
        $balance_after = ($balance_before - $amount);

        $member = $this->memberRepository->find($member_code);

        $money_text = 'จำนวนเงิน '.$amount.' โบนัส '.$bonus.' รวมเป็น '.$total;

        if ((($member->balance - $amount) < 0) || $member->balance != $balance_before) {
            ActivityLoggerUser::activity('Transfer Wallet To Game '.$game_name, $money_text.' พบปัญหายอดเงินในการทำรายการไม่ถูกต้อง');
            $return['msg'] = 'ยอด Wallet คงเหลือไม่ถูกต้อง';
            return $return;
        }

        ActivityLoggerUser::activity('Transfer Wallet To Game '.$game_name, $money_text.' เริ่มต้นทำรายการโยกเงิน');

        $response = $this->gameUserRepository->UserDeposit($game_code, $user_name, $total, false);
        if ($response['success'] !== true) {
            ActivityLoggerUser::activity('Transfer Wallet To Game '.$game_name, $money_text.' ไม่สามารถฝากเงินเข้าเกมได้');
            $return['msg'] = 'ไม่สามารถ ทำรายการโยกเงินเข้าเกมได้';
            return $return;
        }

        ActivityLoggerUser::activity('Transfer Wallet To Game '.$game_name, $money_text.' ระบบทำการฝากเงินเข้าเกมแล้ว');

        $member->balance -= $amount;
        $member->save();

        DB::beginTransaction();

        try {
            $member = $this->memberRepository->find($member_code);



            $bill = $this->create([
                'enable' => 'Y',
                'ref_id' => $response['ref_id'],
                'credit_before' => $response['before'],
                'credit_after' => $response['after'],
                'member_code' => $member_code,
                'game_code' => $game_code,
                'pro_code' => $pro_code,
                'transfer_type' => 1,
                'amount' => $amount,
                'balance_before' => $balance_before,
                'balance_after' => $balance_after,
                'credit' => $amount,
                'credit_bonus' => $bonus,
                'credit_balance' => $total,
                'ip' => $ip,
                'user_create' => $member['name'],
                'user_update' => $member['name']
            ]);

            $this->gameUserRepository->update([
                'balance' => $response['after'],
                'pro_code' => $pro_code,
                'bill_code' => $bill->code,
                'turnpro' => $turnpro,
                'amount' => $amount,
                'bonus' => $bonus,
                'amount_balance' => ($total * $turnpro),
                'withdraw_limit' => $withdraw_limit
            ], $user_code);

            if ($pro_code == 1) {
                $member->status_pro = 1;
            }
            if ($pro_code > 0) {
                $member->pro_status = 'Y';
                $member->promotion = 'Y';

                $this->memberPromotionLogRepository->create([
                    'date_start' => now()->toDateString(),
                    'bill_code' => $bill->code,
                    'member_code' => $member_code,
                    'game_code' => $game_code,
                    'game_name' => $game_name,
                    'gameuser_code' => $user_code,
                    'pro_code' => $pro_code,
                    'pro_name' => $pro_name,
                    'turnpro' => $turnpro,
                    'amount' => $amount,
                    'bonus' => $bonus,
                    'amount_balance' => ($total * $turnpro),
                    'withdraw_limit' => $withdraw_limit,
                    'complete' => 'N',
                    'enable' => 'Y',
                    'user_create' => $member['name'],
                    'user_update' => $member['name']
                ]);
            }

            $member->save();


            $this->paymentLogRepository->create([
                'msg' => 'โยกเงินออกจาก Wallet เข้าเกม เรียบร้อย',
                'status' => 'COMPLETE',
                'showmsg' => 'Y',
                'confirm' => 'Y',
                'enable' => 'Y',
                'bill_code' => $bill->code,
                'member_code' => $member_code,
                'game_code' => $game_code,
                'token' => '',
                'transfer_type' => 1,
                'amount' => $amount,
                'ip' => $ip,
                'user_create' => $member['name'],
                'user_update' => $member['name']
            ]);


            $this->memberLogRepository->create([
                'member_code' => $member_code,
                'mode' => 'TRANSFER_IN',
                'menu' => 'transfergame',
                'record' => $member_code,
                'remark' => 'โยกเงินออกจาก Wallet เข้าเกม '.$money_text,
                'item_before' => '',
                'item' => serialize($data),
                'ip' => $ip,
                'user_create' => $member['name']
            ]);

            $this->memberCreditLogRepository->create([
                'ip' => $ip,
                'credit_type' => 'W',
                'amount' => $amount,
                'bonus' => 0,
                'total' => $amount,
                'balance_before' => $balance_before,
                'balance_after' => $balance_after,
                'credit' => $amount,
                'credit_bonus' => $bonus,
                'credit_total' => $total,
                'credit_before' => $response['before'],
                'credit_after' => $response['after'],
                'member_code' => $member_code,
                'game_code' => $game_code,
                'gameuser_code' => $user_code,
                'pro_code' => $pro_code,
                'bank_code' => 0,
                'refer_code' => $bill->code,
                'refer_table' => 'bills',
                'auto' => 'N',
                'remark' => "โยกเงินจาก Wallet เข้าเกม  อ้างอิงบิล ID :" . $bill->code,
                'kind' => 'TRANSFER',
                'user_create' => $member['name'],
                'user_update' => $member['name']
            ]);

            $member->bankPayments()->where('member_topup', $member_code)->where('pro_check', 'N')->update([
                'pro_check' => 'Y',
                'user_update' => $member['name']
            ]);

            DB::commit();


        } catch (Throwable $e) {
            ActivityLoggerUser::activity('Transfer Wallet To Game '.$game_name, $money_text.' พบปัญหาในการทำรายการ');
            DB::rollBack();
            ActivityLoggerUser::activity('Transfer Wallet ToGame '.$game_name, $money_text.' ดำเนินการ Rollback การทำรายการแล้ว');

            $response = $this->gameUserRepository->UserWithdraw($game_code, $user_name, $total);
            if ($response['success'] === true) {
                ActivityLoggerUser::activity('Transfer Wallet ToGame '.$game_name, $money_text.' ระบบทำการถอนเงินออกจากเกมแล้ว');
                $member->balance += $amount;
                $member->save();
                ActivityLoggerUser::activity('Transfer Wallet ToGame '.$game_name, $money_text.' ระบบทำการคืนยอด Wallet แล้ว');
            } else {
                ActivityLoggerUser::activity('Transfer Wallet To Game '.$game_name, $money_text.' ระบบไม่สามารถถอนเงินออกจากเกมได้ จึงไม่คืน Wallet');
            }


            $return['msg'] = 'ไม่สามารถทำรายการได้ โปรดลองใหม่อีกครั้ง';
            report($e);
            return $return;
        }

        ActivityLoggerUser::activity('Transfer Wallet To Game '.$game_name, $money_text.' ทำรายการโยกเงินสำเร็จ');
        $return['success'] = true;
        $return['data'] = $bill;
        return $return;


    }

    public function requestTransferGame(array $data): array
    {
        $return['success'] = false;

        $ip = request()->ip();

        $member_code = $data['member_code'];
        $game_code = $data['game_code'];
        $game_name = $data['game_name'];
        $user_code = $data['user_code'];
        $user_name = $data['user_name'];
        $pro_code = $data['pro_code'];
        $pro_name = $data['pro_name'];
        $turnpro = $data['turnpro'];
        $amount = $data['amount'];
        $bonus = $data['bonus'];
        $total = $data['total'];
        $balance_before = $data['member_balance'];
        $balance_after = ($balance_before - $amount);

        $member = $this->memberRepository->find($member_code);


        if ((($member->balance - $amount) < 0) || $member->balance != $balance_before) {
            ActivityLoggerUser::activity('Request Transfer Wallet To Game '.$game_name, 'จำนวนเงิน '.$total.' พบปัญหายอดเงินในการทำรายการไม่ถูกต้อง');
            $return['msg'] = 'ยอด Wallet คงเหลือไม่ถูกต้อง';
            return $return;
        }

        ActivityLoggerUser::activity('Request Transfer Wallet To Game '.$game_name, 'จำนวนเงิน '.$total.' เริ่มต้นทำรายการแจ้งทีมงานเพื่อโยกเงิน');


        DB::beginTransaction();
        try {

            $member = $this->memberRepository->find($member_code);

            $bill = $this->paymentWaitingRepository->create([
                'member_code' => $member_code,
                'game_code' => $game_code,
                'pro_code' => $pro_code,
                'transfer_type' => 1,
                'amount' => $amount,
                'bonus' => $bonus,
                'total' => $total,
                'ip' => $ip,
                'user_create' => $member->name,
                'user_update' => $member->name,
            ]);


            $this->memberLogRepository->create([
                'member_code' => $member_code,
                'mode' => 'TRANSFER_IN_REQUEST',
                'menu' => 'transfergame',
                'record' => $member_code,
                'remark' => 'แจ้งโยกเงินออกจาก Wallet เข้าเกม',
                'item_before' => '',
                'item' => serialize($data),
                'ip' => $ip,
                'user_create' => $member['name']
            ]);

            $this->memberCreditLogRepository->create([
                'ip' => $ip,
                'credit_type' => 'W',
                'amount' => $amount,
                'bonus' => 0,
                'total' => $amount,
                'balance_before' => $balance_before,
                'balance_after' => $balance_after,
                'credit' => 0,
                'credit_bonus' => 0,
                'credit_total' => 0,
                'credit_before' => 0,
                'credit_after' => 0,
                'member_code' => $member_code,
                'game_code' => $game_code,
                'gameuser_code' => $user_code,
                'bank_code' => 0,
                'refer_code' => $bill->code,
                'refer_table' => 'payments_waiting',
                'auto' => 'N',
                'remark' => "แจ้งโยกเงินจาก Wallet เข้าเกม  อ้างอิงบิล ID :" . $bill->code,
                'kind' => 'TRANSFER',
                'user_create' => $member['name'],
                'user_update' => $member['name']
            ]);

            $member->bankPayments()->where('member_topup', $member_code)->where('pro_check', 'N')->update([
                'pro_check' => 'Y',
                'user_update' => $member['name']
            ]);


            $member->balance = $balance_after;
            $member->save();

            DB::commit();
        } catch (Throwable $e) {
            ActivityLoggerUser::activity('Request Transfer Wallet To Game '.$game_name, 'จำนวนเงิน '.$total.' พบปัญหาในการทำรายการ');
            DB::rollBack();
            ActivityLoggerUser::activity('Request Transfer Wallet To Game '.$game_name, 'จำนวนเงิน '.$total.' ดำเนินการ Rollback การทำรายการแล้ว');

            $return['msg'] = 'ไม่สามารถทำรายการได้ โปรดลองใหม่อีกครั้ง';
            report($e);
            return $return;
        }


        ActivityLoggerUser::activity('Request Transfer Wallet To Game '.$game_name, 'จำนวนเงิน '.$total.' ทำรายการแจ้งทีมงานเพื่อโยกเงินสำเร็จ');

        $return['success'] = true;
        $return['data'] = $bill;
        return $return;

    }

    public function confirmWallet(array $data): array
    {
        $return['success'] = false;

        $ip = request()->ip();

        $member_code = $data['member_code'];
        $game_code = $data['game_code'];
        $game_name = $data['game_name'];
        $user_code = $data['user_code'];
        $user_name = $data['user_name'];
        $pro_code = $data['pro_code'];
        $pro_name = $data['pro_name'];
        $emp_code = $data['emp_code'];
        $emp_name = $data['emp_name'];
        $turnpro = $data['turnpro'];
        $withdraw_limit = $data['withdraw_limit'];
        $amount = $data['amount'];
        $bonus = $data['bonus'];
        $total = $data['total'];
        $payment_code = $data['payment_code'];
        $balance_before = $data['member_balance'];
        $balance_after = ($balance_before - $amount);

        $member = $this->memberRepository->find($member_code);

        ActivityLogger::activitie('Confirm Transfer Wallet To Game User : ' . $member->user_name, 'เริ่มต้นทำรายการยืนยันการโยกเงิน');

        $response = $this->gameUserRepository->UserDeposit($game_code, $user_name, $total, false);
        if ($response['success'] !== true) {
            ActivityLogger::activitie('Confirm Transfer Wallet To Game User : ' . $member->user_name, 'ไม่สามารถฝากเงินเข้าเกมได้');
            $return['msg'] = 'ไม่สามารถ ทำรายการโยกเงินเข้าเกมได้';
            return $return;
        }

        ActivityLogger::activitie('Confirm Transfer Wallet To Game User : ' . $member->user_name, 'ระบบทำการฝากเงินเข้าเกมแล้ว');

        DB::beginTransaction();

        try {

            $bill = $this->create([
                'enable' => 'Y',
                'ref_id' => $response['ref_id'],
                'credit_before' => $response['before'],
                'credit_after' => $response['after'],
                'member_code' => $member_code,
                'game_code' => $game_code,
                'pro_code' => $pro_code,
                'transfer_type' => 1,
                'amount' => $amount,
                'balance_before' => $balance_before,
                'balance_after' => $balance_after,
                'credit' => $amount,
                'credit_bonus' => $bonus,
                'credit_balance' => $total,
                'emp_code' => $emp_code,
                'ip' => $ip,
                'user_create' => $emp_name,
                'user_update' => $emp_name
            ]);


            $this->paymentLogRepository->create([
                'msg' => 'ทีมงานอนุมัติ การโยกเงินออกจาก Wallet เข้าเกม เรียบร้อย',
                'status' => 'COMPLETE',
                'showmsg' => 'Y',
                'confirm' => 'Y',
                'enable' => 'Y',
                'bill_code' => $bill->code,
                'member_code' => $member_code,
                'game_code' => $game_code,
                'token' => '',
                'transfer_type' => 1,
                'amount' => $amount,
                'ip' => $ip,
                'user_create' => $emp_name,
                'user_update' => $emp_name
            ]);


            $this->memberLogRepository->create([
                'member_code' => $member_code,
                'mode' => 'TRANSFER_IN',
                'menu' => 'payments_waiting',
                'record' => $member_code,
                'remark' => 'ทีมงานอนุมัติ การโยกเงินออกจาก Wallet เข้าเกม',
                'item_before' => '',
                'item' => serialize($data),
                'ip' => $ip,
                'user_create' => $member['name']
            ]);

            $this->memberCreditLogRepository->create([
                'ip' => $ip,
                'credit_type' => 'W',
                'amount' => $amount,
                'bonus' => 0,
                'total' => $amount,
                'balance_before' => $balance_before,
                'balance_after' => $balance_after,
                'credit' => $amount,
                'credit_bonus' => $bonus,
                'credit_total' => $total,
                'credit_before' => $response['before'],
                'credit_after' => $response['after'],
                'member_code' => $member_code,
                'game_code' => $game_code,
                'gameuser_code' => $user_code,
                'pro_code' => $pro_code,
                'bank_code' => 0,
                'refer_code' => $bill->code,
                'refer_table' => 'bills',
                'emp_code' => $emp_code,
                'auto' => 'N',
                'remark' => "ทีมงานอนุมัติ การโยกเงินจาก Wallet เข้าเกม อ้างอิงบิล ID :" . $bill->code,
                'kind' => 'CONFIRM',
                'user_create' => $emp_name,
                'user_update' => $emp_name
            ]);


            $this->paymentWaitingRepository->update([
                'credit' => $amount,
                'credit_before' => $response['before'],
                'credit_after' => $response['after'],
                'credit_balance' => $total,
                'ip_admin' => $ip,
                'confirm' => 'Y',
                'date_approve' => now()->toDateTimeString(),
                'emp_code' => $emp_code,
                'user_update' => $emp_name,
            ], $payment_code);


            if ($pro_code == 1) {
                $member->status_pro = 1;
            }
            if ($pro_code > 0) {
                $member->pro_status = 'Y';
                $member->promotion = 'Y';

                $this->memberPromotionLogRepository->create([
                    'date_start' => now()->toDateString(),
                    'bill_code' => $bill->code,
                    'member_code' => $member_code,
                    'game_code' => $game_code,
                    'game_name' => $game_name,
                    'gameuser_code' => $user_code,
                    'pro_code' => $pro_code,
                    'pro_name' => $pro_name,
                    'turnpro' => $turnpro,
                    'amount' => $amount,
                    'bonus' => $bonus,
                    'amount_balance' => ($total * $turnpro),
                    'withdraw_limit' => $withdraw_limit,
                    'complete' => 'N',
                    'enable' => 'Y',
                    'emp_code' => $emp_code,
                    'user_create' => $emp_name,
                    'user_update' => $emp_name
                ]);
            }

            $this->gameUserRepository->update([
                'balance' => $response['after'],
                'pro_code' => $pro_code,
                'bill_code' => $bill->code,
                'turnpro' => $turnpro,
                'amount' => $amount,
                'bonus' => $bonus,
                'amount_balance' => ($total * $turnpro),
                'withdraw_limit' => $withdraw_limit
            ], $user_code);

            $member->save();
            DB::commit();

        } catch (Throwable $e) {
            ActivityLogger::activitie('Confirm Transfer Wallet To Game User : ' . $member->user_name, 'พบปัญหาในการทำรายการ');
            DB::rollBack();
            ActivityLogger::activitie('Reject Transfer Wallet To Game User : ' . $member->user_name, 'ดำเนินการ Rollback การทำรายการแล้ว');

            $response = $this->gameUserRepository->UserWithdraw($game_code, $user_name, $total);
            if ($response['success'] === true) {
                ActivityLogger::activitie('Confirm Transfer Wallet To Game User : ' . $member->user_name, 'ระบบทำการถอนเงินออกจากเกมแล้ว');

            } else {
                ActivityLogger::activitie('Confirm Transfer Wallet To Game User : ' . $member->user_name, 'ระบบไม่สามารถถอนเงินออกจากเกมได้');
            }

            $return['msg'] = 'ไม่สามารถทำรายการได้ โปรดลองใหม่อีกครั้ง';
            report($e);
            return $return;
        }


        ActivityLogger::activitie('Confirm Transfer Wallet To Game User : ' . $member->user_name, 'ทำรายการยืนยันการโยกเงินสำเร็จ');


        $return['success'] = true;
        $return['data'] = $bill;
        return $return;

    }

    public function rejectWallet(array $data): array
    {
        $return['success'] = false;

        $ip = request()->ip();


        $member_code = $data['member_code'];
        $game_code = $data['game_code'];
        $game_name = $data['game_name'];
        $user_code = $data['user_code'];
        $user_name = $data['user_name'];
        $pro_code = $data['pro_code'];
        $pro_name = $data['pro_name'];
        $emp_code = $data['emp_code'];
        $emp_name = $data['emp_name'];
        $turnpro = $data['turnpro'];
        $amount = $data['amount'];
        $bonus = $data['bonus'];
        $total = $data['total'];
        $remark = $data['remark'];
        $payment_code = $data['payment_code'];
        $balance_before = $data['member_balance'];
        $balance_after = ($balance_before + $amount);

        $member = $this->memberRepository->find($member_code);

        ActivityLogger::activitie('Reject Transfer Wallet To Game User : ' . $member->user_name, 'เริ่มต้นทำรายการคืนยอดการแจ้งโยกเงิน');


        DB::beginTransaction();

        try {


            $bill = $this->memberCreditLogRepository->create([

                'ip' => $ip,
                'credit_type' => 'D',
                'amount' => $amount,
                'bonus' => 0,
                'total' => $amount,
                'balance_before' => $member->balance,
                'balance_after' => ($member->balance + $amount),
                'credit' => 0,
                'credit_bonus' => 0,
                'credit_total' => 0,
                'credit_before' => 0,
                'credit_after' => 0,
                'member_code' => $member_code,
                'game_code' => $game_code,
                'gameuser_code' => $user_code,
                'pro_code' => 0,
                'bank_code' => 0,
                'refer_code' => $payment_code,
                'refer_table' => 'payments_waiting',
                'emp_code' => $emp_code,
                'auto' => 'N',
                'remark' => "ทีมงานคืนยอด การโยกเงินจาก Wallet เข้าเกม อ้างอิงบิล ID :" . $payment_code,
                'kind' => 'ROLLBACK',
                'user_create' => $emp_name,
                'user_update' => $emp_name
            ]);

            $this->paymentWaitingRepository->update([
                'ip_admin' => $ip,
                'confirm' => 'N',
                'remark' => $remark,
                'emp_code' => $emp_code,
                'user_update' => $emp_name,
            ], $payment_code);

            $member->balance = ($member->balance + $amount);
            $member->save();

            DB::commit();

        } catch (Throwable $e) {
            ActivityLogger::activitie('Reject Transfer Wallet To Game User : ' . $member->user_name, 'พบปัญหาในการทำรายการ');
            DB::rollBack();
            ActivityLogger::activitie('Reject Transfer Wallet To Game User : ' . $member->user_name, 'ดำเนินการ Rollback การทำรายการแล้ว');

            $return['msg'] = 'ไม่สามารถทำรายการได้ โปรดลองใหม่อีกครั้ง';
            report($e);
            return $return;
        }


        ActivityLogger::activitie('Reject Transfer Wallet To Game User : ' . $member->user_name, 'ทำรายการคืนยอดการแจ้งโยกเงินสำเร็จ');

        $return['success'] = true;
        $return['data'] = $bill;
        return $return;

    }
}
