<?php

namespace Gametech\Member\Repositories;

use App\Notifications\RealTimeNotification;
use Gametech\Core\Eloquent\Repository;
use Gametech\Game\Repositories\GameUserEventRepository;
use Gametech\Game\Repositories\GameUserRepository;
use Gametech\LogUser\Http\Traits\ActivityLoggerUser;
use Gametech\Promotion\Repositories\PromotionRepository;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Notification;
use Throwable;

class MemberCreditLogRepository extends Repository
{
    use ActivityLoggerUser;

    protected $promotionRepository;

    private $memberRepository;

    private $gameUserRepository;

    private $gameUserEventRepository;

    public function __construct(
        MemberRepository $memberRepo,
        GameUserRepository $gameUserRepo,
        GameUserEventRepository $gameUserEventRepo,
        PromotionRepository $promotionRepo,
        App $app
    ) {
        $this->memberRepository = $memberRepo;
        $this->gameUserRepository = $gameUserRepo;
        $this->gameUserEventRepository = $gameUserEventRepo;
        $this->promotionRepository = $promotionRepo;
        parent::__construct($app);
    }

    /**
     * Specify Model class name
     */
    public function model(): string
    {
        return \Gametech\Member\Models\MemberCreditLog::class;

    }

    public function setBonus(array $data): bool
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
        $refer_code = $data['refer_code'];
        $refer_table = $data['refer_table'];

        $member = $this->memberRepository->find($member_code);

        $promotion = DB::table('promotions')->where('id', 'pro_spin')->first();
        if ($promotion) {

            $pro_code = $promotion->code;
            $pro_name = $promotion->name_th;
            $turnpro = $promotion->turnpro;
            $withdraw_limit = $promotion->withdraw_limit;
            $withdraw_limit_rate = $promotion->withdraw_limit_rate;
        } else {
            $pro_code = 0;
            $pro_name = '';
            $turnpro = 0;
            $withdraw_limit = 0;
            $withdraw_limit_rate = 0;
        }

        $game = core()->getGame();
        $game_user = $this->gameUserEventRepository->findOneWhere(['method' => 'BONUS', 'member_code' => $member->code, 'game_code' => $game->code, 'enable' => 'Y']);
        if (! $game_user) {
            $game_user = $this->gameUserEventRepository->create([
                'game_code' => $game->code,
                'member_code' => $member->code,
                'pro_code' => 0,
                'method' => 'BONUS',
                'user_name' => $member->user_name,
                'amount' => 0,
                'bonus' => 0,
                'turnpro' => 0,
                'amount_balance' => 0,
                'withdraw_limit' => 0,
                'withdraw_limit_rate' => 0,
                'withdraw_limit_amount' => 0,
            ]);
        }

        //        DB::beginTransaction();
        try {

            if ($method == 'D') {
                $game_user->bonus += $amount;
                $member->bonus += $amount;
            } elseif ($method == 'W') {
                $game_user->bonus -= $amount;
                $member->bonus -= $amount;
                if ($game_user->bonus < 0) {
                    return false;
                }
            }

            $game_user->amount = $member->balance;

            $game_user->pro_code = $pro_code;
            $game_user->bill_code = 0;
            $game_user->turnpro = $turnpro;
            $game_user->amount_balance += ($amount * $turnpro);
            $game_user->withdraw_limit += $withdraw_limit;
            $game_user->withdraw_limit_rate = $withdraw_limit_rate;
            $game_user->withdraw_limit_amount += ($amount * $withdraw_limit_rate);

            $game_user->save();

            $member->save();

            $this->create([
                'refer_code' => $refer_code,
                'refer_table' => $refer_table,
                'credit_type' => $method,
                'pro_code' => $pro_code,
                'game_code' => $game->code,
                'amount' => 0,
                'bonus' => $amount,
                'total' => $amount,
                'balance_before' => 0,
                'balance_after' => 0,
                'credit' => 0,
                'credit_bonus' => 0,
                'credit_total' => 0,
                'credit_before' => 0,
                'credit_after' => 0,
                'member_code' => $member_code,
                'user_name' => $member->user_name,
                'kind' => $kind,
                'auto' => 'N',
                'remark' => $remark,
                'emp_code' => $emp_code,
                'ip' => $ip,
                'amount_balance' => $game_user->amount_balance,
                'withdraw_limit' => $game_user->withdraw_limit,
                'withdraw_limit_amount' => $game_user->withdraw_limit_amount,
                'user_create' => $emp_name,
                'user_update' => $emp_name,
            ]);

            //            DB::commit();

        } catch (Throwable $e) {
            //            DB::rollBack();
            report($e);

            return false;
        }

        return true;
    }

    public function setBonus_(array $data): bool
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
        $refer_code = $data['refer_code'];
        $refer_table = $data['refer_table'];

        $member = $this->memberRepository->find($member_code);

        if ($method == 'D') {
            $credit_balance = ($member->credit + $amount);
        } elseif ($method == 'W') {
            $credit_balance = ($member->credit - $amount);
            if ($credit_balance < 0) {
                return false;
            }
        }

        //        DB::beginTransaction();
        try {

            $member->credit += $amount;
            $member->save();

            //            DB::commit();

        } catch (Throwable $e) {
            //            DB::rollBack();
            report($e);

            return false;
        }

        return true;
    }

    public function setWallet(array $data): bool
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
        $refer_code = $data['refer_code'];
        $refer_table = $data['refer_table'];

        $member = $this->memberRepository->find($member_code);

        if ($method == 'D') {

            $credit_balance = ($member->balance + $amount);
            //            $member->balance += $amount;

        } elseif ($method == 'W') {
            $credit_balance = ($member->balance - $amount);
            //            $member->balance -= $amount;

            if ($credit_balance < 0) {
                return false;
            }
        }

        $this->create([
            'refer_code' => $refer_code,
            'refer_table' => $refer_table,
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
            'kind' => $kind,
            'auto' => 'N',
            'remark' => $remark,
            'emp_code' => $emp_code,
            'ip' => $ip,
            'user_create' => $emp_name,
            'user_update' => $emp_name,
        ]);

        if ($method == 'D') {
            $member->balance += $amount;

        } else {
            $member->balance -= $amount;
        }
        //        $member->balance = $credit_balance;
        $member->save();

        //        DB::beginTransaction();
        //        try {
        //
        //            $this->create([
        //                'refer_code' => $refer_code,
        //                'refer_table' => $refer_table,
        //                'credit_type' => $method,
        //                'amount' => $amount,
        //                'bonus' => 0,
        //                'total' => $amount,
        //                'balance_before' => $member->balance,
        //                'balance_after' => $credit_balance,
        //                'credit' => 0,
        //                'credit_bonus' => 0,
        //                'credit_total' => 0,
        //                'credit_before' => 0,
        //                'credit_after' => 0,
        //                'member_code' => $member_code,
        //                'kind' => $kind,
        //                'auto' => 'N',
        //                'remark' => $remark,
        //                'emp_code' => $emp_code,
        //                'ip' => $ip,
        //                'user_create' => $emp_name,
        //                'user_update' => $emp_name
        //            ]);
        //
        //            $member->balance = $credit_balance;
        //            $member->save();
        //
        //            DB::commit();
        //
        //        } catch (Throwable $e) {
        //            DB::rollBack();
        //            report($e);
        //            return false;
        //        }

        return true;
    }

    public function setWalletSeamless_(array $data): bool
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
        $refer_code = $data['refer_code'];
        $refer_table = $data['refer_table'];

        $member = $this->memberRepository->find($member_code);

        //        $game_user = $this->gameUserRepository->findOneWhere(['member_code' => $member->code , 'enable' => 'Y']);

        if ($method == 'D') {
            $credit_balance = ($member->balance + $amount);
        } elseif ($method == 'W') {
            $credit_balance = ($member->balance - $amount);
            if ($credit_balance < 0) {
                return false;
            }
        }

        $this->create([
            'refer_code' => $refer_code,
            'refer_table' => $refer_table,
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
            'kind' => $kind,
            'auto' => 'N',
            'remark' => $remark,
            'emp_code' => $emp_code,
            'ip' => $ip,
            'user_create' => $emp_name,
            'user_update' => $emp_name,
        ]);

        if ($method == 'D') {
            $member->credit += $amount;
            $member->balance += $amount;

        } else {
            $member->credit -= $amount;
            $member->balance -= $amount;
        }

        $member->save();

        return true;
    }

    public function setWalletSeamlessWithdraw(array $data): bool
    {

        $ip = request()->ip();
        $credit_balance = 0;
        $member_code = $data['member_code'];
        $amount = $data['amount'];
        $amount_balance = $data['amount_balance'];
        $withdraw_limit = $data['withdraw_limit'];
        $withdraw_limit_amount = $data['withdraw_limit_amount'];
        $method = $data['method'];
        $kind = $data['kind'];
        $remark = $data['remark'];
        $emp_code = $data['emp_code'];
        $emp_name = $data['emp_name'];
        $refer_code = $data['refer_code'];
        $refer_table = $data['refer_table'];
        $pro_name = $data['pro_name'];
        $pro_code = $data['pro_code'];

        $member = $this->memberRepository->find($member_code);

        $game = core()->getGame();
        $game_user = $this->gameUserRepository->findOneWhere(['member_code' => $member->code, 'enable' => 'Y']);
        $game_code = $game->code;
        $user_name = $game_user->user_name;
        $user_code = $game_user->code;
        $game_name = $game->name;
        $game_balance = $game_user->balance;
        $member_code = $member->code;

        if ($method == 'D') {
            $credit_balance = ($member->balance + $amount);
        } elseif ($method == 'W') {
            $credit_balance = ($member->balance - $amount);
            if ($credit_balance < 0) {
                return false;
            }
        }

        //        DB::beginTransaction();
        try {

            $this->create([
                'refer_code' => $refer_code,
                'refer_table' => $refer_table,
                'credit_type' => $method,
                'pro_code' => $pro_code,
                'pro_name' => $pro_name,
                'amount_balance' => $amount_balance,
                'withdraw_limit' => $withdraw_limit,
                'withdraw_limit_amount' => $withdraw_limit_amount,
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
                'kind' => $kind,
                'auto' => 'N',
                'remark' => $remark,
                'emp_code' => $emp_code,
                'ip' => $ip,
                'user_create' => $emp_name,
                'user_update' => $emp_name,
            ]);

            app('Gametech\Payment\Repositories\BillRepository')->create([
                'complete' => 'Y',
                'enable' => 'Y',
                'refer_code' => $refer_code,
                'refer_table' => $refer_table,
                'ref_id' => '',
                'credit_before' => $member->balance,
                'credit_after' => $credit_balance,
                'member_code' => $member_code,
                'game_code' => $game_code,
                'gameuser_code' => $user_code,
                'pro_code' => $pro_code,
                'pro_name' => $pro_name,
                'remark' => $remark,
                'method' => $kind,
                'transfer_type' => 1,
                'amount' => $amount,
                'balance_before' => $member->balance,
                'balance_after' => $credit_balance,
                'credit' => $amount,
                'credit_bonus' => 0,
                'credit_balance' => $amount,
                'amount_request' => 0,
                'amount_limit' => 0,
                'ip' => $ip,
                'user_create' => $member['name'],
                'user_update' => $member['name'],
            ]);

            if ($method == 'D') {
                $member->balance += $amount;
                //                $game_user->balance += $amount;
                $game_user->pro_code = $pro_code;
                $game_user->amount_balance += $amount_balance;
                $game_user->withdraw_limit = $withdraw_limit;
                $game_user->withdraw_limit_amount += $withdraw_limit_amount;
            } else {
                $member->balance -= $amount;
                //                $game_user->balance -= $amount;
            }

            $member->save();
            $game_user->save();

            //            DB::commit();

        } catch (Throwable $e) {
            //            DB::rollBack();
            report($e);

            return false;
        }

        Notification::send($member, new RealTimeNotification(Lang::get('app.home.adjust_balance')));

        return true;
    }

    public function setWalletSingleWithdraw(array $data): bool
    {

        $ip = request()->ip();
        $credit_balance = 0;
        $member_code = $data['member_code'];
        $amount = $data['amount'];
        $amount_balance = $data['amount_balance'];
        $withdraw_limit = $data['withdraw_limit'];
        $withdraw_limit_amount = $data['withdraw_limit_amount'];
        $method = $data['method'];
        $kind = $data['kind'];
        $remark = $data['remark'];
        $emp_code = $data['emp_code'];
        $emp_name = $data['emp_name'];
        $refer_code = $data['refer_code'];
        $refer_table = $data['refer_table'];
        $pro_name = $data['pro_name'];
        $pro_code = $data['pro_code'];
        $isDp = $data['isDp'] ?? false;

        $member = $this->memberRepository->find($member_code);

        $game = core()->getGame();
        $game_user = $this->gameUserRepository->findOneWhere(['member_code' => $member->code, 'enable' => 'Y']);
        $game_code = $game->code;
        $user_name = $game_user->user_name;
        $user_code = $game_user->code;
        $game_name = $game->name;
        $game_balance = $game_user->balance;
        $member_code = $member->code;

        if ($method == 'D') {
            $credit_balance = ($member->balance + $amount);
        } elseif ($method == 'W') {
            $credit_balance = ($member->balance - $amount);
            if ($credit_balance < 0) {
                return false;
            }
        }

        $money_text = 'จำนวนเงิน '.$amount;
        //        DB::beginTransaction();
        try {

            $response = $this->gameUserRepository->UserDeposit($game_code, $user_name, $amount, false, $isDp);
            if ($response['success'] === true) {
                ActivityLoggerUser::activity('ฝากเงินเข้าเกม '.$game_name, $money_text.' ระบบทำการฝากเงินเข้าเกมแล้ว', $member_code);

            } else {
                ActivityLoggerUser::activity('ฝากเงินเข้าเกม '.$game_name, $money_text.' ไม่สามารถฝากเงินเข้าเกมได้', $member_code);

                return false;
            }

            $this->create([
                'refer_code' => $refer_code,
                'refer_table' => $refer_table,
                'credit_type' => $method,
                'pro_code' => $pro_code,
                'pro_name' => $pro_name,
                'amount_balance' => $amount_balance,
                'withdraw_limit' => $withdraw_limit,
                'withdraw_limit_amount' => $withdraw_limit_amount,
                'amount' => $amount,
                'bonus' => 0,
                'total' => $amount,
                'balance_before' => $response['before'],
                'balance_after' => $response['after'],
                'credit' => 0,
                'credit_bonus' => 0,
                'credit_total' => 0,
                'credit_before' => $response['before'],
                'credit_after' => $response['after'],
                'member_code' => $member_code,
                'user_name' => $member->user_name,
                'remark' => 'RefID : '.$response['ref_id'].' '.$remark,
                'kind' => $kind,
                'auto' => 'N',
                'emp_code' => $emp_code,
                'ip' => $ip,
                'user_create' => $emp_name,
                'user_update' => $emp_name,
            ]);

            app('Gametech\Payment\Repositories\BillRepository')->create([
                'complete' => 'Y',
                'enable' => 'Y',
                'refer_code' => $refer_code,
                'refer_table' => $refer_table,
                'ref_id' => $response['ref_id'],
                'credit_before' => $response['before'],
                'credit_after' => $response['after'],
                'member_code' => $member_code,
                'game_code' => $game_code,
                'gameuser_code' => $user_code,
                'pro_code' => $pro_code,
                'pro_name' => $pro_name,
                'remark' => $remark,
                'method' => $kind,
                'transfer_type' => 1,
                'amount' => $amount,
                'balance_before' => $response['before'],
                'balance_after' => $response['after'],
                'credit' => $amount,
                'credit_bonus' => 0,
                'credit_balance' => $amount,
                'amount_request' => 0,
                'amount_limit' => 0,
                'ip' => $ip,
                'user_create' => $member['name'],
                'user_update' => $member['name'],
            ]);

            if ($method == 'D') {
                $member->balance = $response['after'];
                $game_user->balance = $response['after'];
                if($pro_code > 0) {
                    if($game_user->pro_code == 0){
                        $game_user->pro_code = $pro_code;
                        $game_user->amount_balance = $amount_balance;
                        $game_user->withdraw_limit = $withdraw_limit;
                        $game_user->withdraw_limit_amount = $withdraw_limit_amount;
                    }else{
                        $game_user->amount_balance += $amount_balance;
                        $game_user->withdraw_limit_amount += $withdraw_limit_amount;
                    }

                }

            } else {
                //                $member->balance -= $amount;
                //                $game_user->balance -= $amount;
            }

            $member->save();
            $game_user->save();

            //            DB::commit();

        } catch (Throwable $e) {
            //            DB::rollBack();
            report($e);

            return false;
        }

        Notification::send($member, new RealTimeNotification(Lang::get('app.home.adjust_balance')));

        return true;
    }

    public function setWalletSingle(array $data): bool
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
        $refer_code = $data['refer_code'];
        $refer_table = $data['refer_table'];
        $isDp = $data['isDp'] ?? false;

        $member = $this->memberRepository->find($member_code);

        $game = core()->getGame();
        $game_user = $this->gameUserRepository->findOneWhere(['member_code' => $member->code, 'game_code' => $game->code, 'enable' => 'Y']);
        $game_code = $game->code;
        $user_name = $game_user->user_name;
        $user_code = $game_user->code;
        $game_name = $game->name;
        $game_balance = $game_user->balance;
        $member_code = $member->code;

        if ($method == 'D') {
            $credit_balance = ($member->balance + $amount);
        } elseif ($method == 'W') {
            $credit_balance = ($member->balance - $amount);
            if ($credit_balance < 0) {
                return false;
            }
        }

        $money_text = 'จำนวนเงิน '.$amount;

        if ($method == 'D') {

            $response = $this->gameUserRepository->UserDeposit($game_code, $user_name, $amount, false, $isDp);
            if ($response['success'] === true) {
                ActivityLoggerUser::activity('ฝากเงินเข้าเกม '.$game_name, $money_text.' ระบบทำการฝากเงินเข้าเกมแล้ว', $member_code);

            } else {
                ActivityLoggerUser::activity('ฝากเงินเข้าเกม '.$game_name, $money_text.' ไม่สามารถฝากเงินเข้าเกมได้', $member_code);

                return false;
            }

            //            DB::beginTransaction();
            try {

                $this->create([
                    'refer_code' => $refer_code,
                    'refer_table' => $refer_table,
                    'credit_type' => $method,
                    'amount' => $amount,
                    'bonus' => 0,
                    'total' => $amount,
                    'balance_before' => 0,
                    'balance_after' => 0,
                    'credit' => 0,
                    'credit_bonus' => 0,
                    'credit_total' => 0,
                    'credit_before' => $response['before'],
                    'credit_after' => $response['after'],
                    'member_code' => $member_code,
                    'gameuser_code' => $user_code,
                    'game_code' => $game_code,
                    'user_name' => $member->user_name,
                    'kind' => $kind,
                    'auto' => 'N',
                    'remark' => 'RefID : '.$response['ref_id'].' '.$remark,
                    'emp_code' => $emp_code,
                    'ip' => $ip,
                    'user_create' => $emp_name,
                    'user_update' => $emp_name,
                ]);

                $member->balance = $response['after'];
                $member->save();

                $game_user->balance = $response['after'];
                $game_user->save();

                app('Gametech\Payment\Repositories\BillRepository')->create([
                    'complete' => 'Y',
                    'enable' => 'Y',
                    'refer_code' => $refer_code,
                    'refer_table' => $refer_table,
                    'ref_id' => $response['ref_id'],
                    'credit_before' => $response['before'],
                    'credit_after' => $response['after'],
                    'member_code' => $member_code,
                    'game_code' => $game_code,
                    'gameuser_code' => $user_code,
                    'pro_code' => 0,
                    'pro_name' => '',
                    'remark' => $remark,
                    'method' => $kind,
                    'transfer_type' => 1,
                    'amount' => $amount,
                    'balance_before' => $response['before'],
                    'balance_after' => $response['after'],
                    'credit' => $amount,
                    'credit_bonus' => 0,
                    'credit_balance' => $amount,
                    'amount_request' => 0,
                    'amount_limit' => 0,
                    'ip' => $ip,
                    'user_create' => $member['name'],
                    'user_update' => $member['name'],
                ]);

                //                DB::commit();

            } catch (Throwable $e) {
                //                DB::rollBack();
                $response = $this->gameUserRepository->UserWithdraw($game_code, $user_name, $amount);
                if ($response['success'] === true) {
                    ActivityLoggerUser::activity('ถอนเงินออกเกม '.$game_name, $money_text.' ระบบทำการถอนเงินออกจากเกมแล้ว');

                } else {
                    ActivityLoggerUser::activity('ถอนเงินออกเกม '.$game_name, $money_text.' ระบบไม่สามารถถอนเงินออกจากเกมได้');
                }
                report($e);

                return false;
            }

        } else {

            $response = $this->gameUserRepository->UserWithdraw($game_code, $user_name, $amount, false);
            if ($response['success'] === true) {
                ActivityLoggerUser::activity('ถอนเงินออกเกม '.$game_name, $money_text.' ระบบทำการถอนเงินออกจากเกมแล้ว');

            } else {
                ActivityLoggerUser::activity('ถอนเงินออกเกม '.$game_name, $money_text.' ไม่สามารถถอนเงินออกจากเกมได้');

                return false;
            }

            //            DB::beginTransaction();
            try {

                $this->create([
                    'refer_code' => $refer_code,
                    'refer_table' => $refer_table,
                    'credit_type' => $method,
                    'amount' => $amount,
                    'bonus' => 0,
                    'total' => $amount,
                    'balance_before' => 0,
                    'balance_after' => 0,
                    'credit' => 0,
                    'credit_bonus' => 0,
                    'credit_total' => 0,
                    'credit_before' => $response['before'],
                    'credit_after' => $response['after'],
                    'member_code' => $member_code,
                    'gameuser_code' => $user_code,
                    'game_code' => $game_code,
                    'user_name' => $member->user_name,
                    'kind' => $kind,
                    'auto' => 'N',
                    'remark' => 'RefID : '.$response['ref_id'].' '.$remark,
                    'emp_code' => $emp_code,
                    'ip' => $ip,
                    'user_create' => $emp_name,
                    'user_update' => $emp_name,
                ]);

                $member->balance = $response['after'];
                $member->save();

                $game_user->balance = $response['after'];
                $game_user->save();

                app('Gametech\Payment\Repositories\BillRepository')->create([
                    'complete' => 'Y',
                    'enable' => 'Y',
                    'refer_code' => $refer_code,
                    'refer_table' => $refer_table,
                    'ref_id' => $response['ref_id'],
                    'credit_before' => $response['before'],
                    'credit_after' => $response['after'],
                    'member_code' => $member_code,
                    'game_code' => $game_code,
                    'gameuser_code' => $user_code,
                    'pro_code' => 0,
                    'pro_name' => '',
                    'remark' => $remark,
                    'method' => $kind,
                    'transfer_type' => 2,
                    'amount' => $amount,
                    'balance_before' => $response['before'],
                    'balance_after' => $response['after'],
                    'credit' => $amount,
                    'credit_bonus' => 0,
                    'credit_balance' => $amount,
                    'amount_request' => 0,
                    'amount_limit' => 0,
                    'ip' => $ip,
                    'user_create' => $member['name'],
                    'user_update' => $member['name'],
                ]);

                //                DB::commit();

            } catch (Throwable $e) {
                //                DB::rollBack();
                $response = $this->gameUserRepository->UserDeposit($game_code, $user_name, $amount, true, $isDp);
                if ($response['success'] === true) {
                    ActivityLoggerUser::activity('ฝากเงินเข้าเกม '.$game_name, $money_text.' ระบบทำการฝากเงินคืนเข้าเกมแล้ว');

                } else {
                    ActivityLoggerUser::activity('ฝากเงินเข้าเกม '.$game_name, $money_text.' ระบบไม่สามารถฝากเงินคืนเข้าเกมได้');
                }
                report($e);

                return false;
            }

        }

        Notification::send($member, new RealTimeNotification(Lang::get('app.home.adjust_balance')));

        return true;
    }

    public function setWalletSeamless(array $data): bool
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
        $refer_code = $data['refer_code'];
        $refer_table = $data['refer_table'];

        $member = $this->memberRepository->find($member_code);

        $game = core()->getGame();
        $game_user = $this->gameUserRepository->findOneWhere(['member_code' => $member->code, 'game_code' => $game->code, 'enable' => 'Y']);
        $game_code = $game->code;
        $user_name = $game_user->user_name;
        $user_code = $game_user->code;
        $game_name = $game->name;
        $game_balance = $game_user->balance;
        $member_code = $member->code;

        if ($method == 'D') {
            $credit_balance = ($member->balance + $amount);
        } elseif ($method == 'W') {
            $credit_balance = ($member->balance - $amount);
            if ($credit_balance < 0) {
                return false;
            }
        }

        $money_text = 'จำนวนเงิน '.$amount;

        if ($method == 'D') {

            $response['before'] = $member->balance;
            $response['after'] = ($member->balance + $amount);
            $response['ref_id'] = '';

            //            DB::beginTransaction();
            try {

                $this->create([
                    'refer_code' => $refer_code,
                    'refer_table' => $refer_table,
                    'credit_type' => $method,
                    'amount' => $amount,
                    'bonus' => 0,
                    'total' => $amount,
                    'balance_before' => 0,
                    'balance_after' => 0,
                    'credit' => 0,
                    'credit_bonus' => 0,
                    'credit_total' => 0,
                    'credit_before' => $response['before'],
                    'credit_after' => $response['after'],
                    'member_code' => $member_code,
                    'gameuser_code' => $user_code,
                    'game_code' => $game_code,
                    'user_name' => $member->user_name,
                    'kind' => $kind,
                    'auto' => 'N',
                    'remark' => 'RefID : '.$response['ref_id'].' '.$remark,
                    'emp_code' => $emp_code,
                    'ip' => $ip,
                    'user_create' => $emp_name,
                    'user_update' => $emp_name,
                ]);

                $member->balance = $response['after'];
                $member->save();

                $game_user->balance = $response['after'];
                $game_user->save();

                app('Gametech\Payment\Repositories\BillRepository')->create([
                    'complete' => 'Y',
                    'enable' => 'Y',
                    'refer_code' => $refer_code,
                    'refer_table' => $refer_table,
                    'ref_id' => $response['ref_id'],
                    'credit_before' => $response['before'],
                    'credit_after' => $response['after'],
                    'member_code' => $member_code,
                    'game_code' => $game_code,
                    'gameuser_code' => $user_code,
                    'pro_code' => 0,
                    'pro_name' => '',
                    'remark' => $remark,
                    'method' => $kind,
                    'transfer_type' => 1,
                    'amount' => $amount,
                    'balance_before' => $response['before'],
                    'balance_after' => $response['after'],
                    'credit' => $amount,
                    'credit_bonus' => 0,
                    'credit_balance' => $amount,
                    'amount_request' => 0,
                    'amount_limit' => 0,
                    'ip' => $ip,
                    'user_create' => $member['name'],
                    'user_update' => $member['name'],
                ]);

                //                DB::commit();

            } catch (Throwable $e) {
                //                DB::rollBack();
                //                $response = $this->gameUserRepository->UserWithdraw($game_code, $user_name, $amount);
                //                if ($response['success'] === true) {
                //                    ActivityLoggerUser::activity('ถอนเงินออกเกม '.$game_name, $money_text.' ระบบทำการถอนเงินออกจากเกมแล้ว');
                //
                //                } else {
                //                    ActivityLoggerUser::activity('ถอนเงินออกเกม '.$game_name, $money_text.' ระบบไม่สามารถถอนเงินออกจากเกมได้');
                //                }
                report($e);

                return false;
            }

        } else {

            $response['before'] = $member->balance;
            $response['after'] = ($member->balance - $amount);
            $response['ref_id'] = '';

            //            DB::beginTransaction();
            try {

                $this->create([
                    'refer_code' => $refer_code,
                    'refer_table' => $refer_table,
                    'credit_type' => $method,
                    'amount' => $amount,
                    'bonus' => 0,
                    'total' => $amount,
                    'balance_before' => 0,
                    'balance_after' => 0,
                    'credit' => 0,
                    'credit_bonus' => 0,
                    'credit_total' => 0,
                    'credit_before' => $response['before'],
                    'credit_after' => $response['after'],
                    'member_code' => $member_code,
                    'gameuser_code' => $user_code,
                    'game_code' => $game_code,
                    'user_name' => $member->user_name,
                    'kind' => $kind,
                    'auto' => 'N',
                    'remark' => 'RefID : '.$response['ref_id'].' '.$remark,
                    'emp_code' => $emp_code,
                    'ip' => $ip,
                    'user_create' => $emp_name,
                    'user_update' => $emp_name,
                ]);

                $member->balance = $response['after'];
                $member->save();

                $game_user->balance = $response['after'];
                $game_user->save();

                app('Gametech\Payment\Repositories\BillRepository')->create([
                    'complete' => 'Y',
                    'enable' => 'Y',
                    'refer_code' => $refer_code,
                    'refer_table' => $refer_table,
                    'ref_id' => $response['ref_id'],
                    'credit_before' => $response['before'],
                    'credit_after' => $response['after'],
                    'member_code' => $member_code,
                    'game_code' => $game_code,
                    'gameuser_code' => $user_code,
                    'pro_code' => 0,
                    'pro_name' => '',
                    'remark' => $remark,
                    'method' => $kind,
                    'transfer_type' => 2,
                    'amount' => $amount,
                    'balance_before' => $response['before'],
                    'balance_after' => $response['after'],
                    'credit' => $amount,
                    'credit_bonus' => 0,
                    'credit_balance' => $amount,
                    'amount_request' => 0,
                    'amount_limit' => 0,
                    'ip' => $ip,
                    'user_create' => $member['name'],
                    'user_update' => $member['name'],
                ]);

                //                DB::commit();

            } catch (Throwable $e) {
                //                DB::rollBack();

                report($e);

                return false;
            }

        }

        Notification::send($member, new RealTimeNotification(Lang::get('app.home.adjust_balance')));

        return true;
    }

    public function setWalletSeamless__(array $data): bool
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
        $refer_code = $data['refer_code'];
        $refer_table = $data['refer_table'];
        //        $isDp = $data['isDp'];

        $member = $this->memberRepository->find($member_code);

        $game = core()->getGame();
        $game_user = $this->gameUserRepository->findOneWhere(['member_code' => $member->code, 'game_code' => $game->code, 'enable' => 'Y']);
        $game_code = $game->code;
        $user_name = $game_user->user_name;
        $user_code = $game_user->code;
        $game_name = $game->name;
        $game_balance = $game_user->balance;
        $member_code = $member->code;

        if ($method == 'D') {
            $credit_balance = ($member->balance + $amount);
        } elseif ($method == 'W') {
            $credit_balance = ($member->balance - $amount);
            if ($credit_balance < 0) {
                return false;
            }
        }

        $money_text = 'จำนวนเงิน '.$amount;

        if ($method == 'D') {

            //            DB::beginTransaction();
            try {

                $this->create([
                    'refer_code' => $refer_code,
                    'refer_table' => $refer_table,
                    'credit_type' => $method,
                    'amount' => $amount,
                    'bonus' => 0,
                    'total' => $amount,
                    'balance_before' => 0,
                    'balance_after' => 0,
                    'credit' => 0,
                    'credit_bonus' => 0,
                    'credit_total' => 0,
                    'credit_before' => $member->balance,
                    'credit_after' => ($member->balance + $amount),
                    'member_code' => $member_code,
                    'gameuser_code' => $user_code,
                    'game_code' => $game_code,
                    'user_name' => $member->user_name,
                    'kind' => $kind,
                    'auto' => 'N',
                    'remark' => $remark,
                    'emp_code' => $emp_code,
                    'ip' => $ip,
                    'user_create' => $emp_name,
                    'user_update' => $emp_name,
                ]);

                app('Gametech\Payment\Repositories\BillRepository')->create([
                    'complete' => 'Y',
                    'enable' => 'Y',
                    'refer_code' => $refer_code,
                    'refer_table' => $refer_table,
                    'ref_id' => '',
                    'credit_before' => $member->balance,
                    'credit_after' => ($member->balance + $amount),
                    'member_code' => $member_code,
                    'game_code' => $game_code,
                    'gameuser_code' => $user_code,
                    'pro_code' => 0,
                    'pro_name' => '',
                    'remark' => $remark,
                    'method' => $kind,
                    'transfer_type' => 1,
                    'amount' => $amount,
                    'balance_before' => $member->balance,
                    'balance_after' => ($member->balance + $amount),
                    'credit' => $amount,
                    'credit_bonus' => 0,
                    'credit_balance' => $amount,
                    'amount_request' => 0,
                    'amount_limit' => 0,
                    'ip' => $ip,
                    'user_create' => $member['name'],
                    'user_update' => $member['name'],
                ]);

                $member->balance += $amount;
                $member->save();

                $game_user->balance = $member->balance;
                $game_user->save();

                //                DB::commit();

            } catch (Throwable $e) {
                //                DB::rollBack();

                report($e);

                return false;
            }

        } else {

            //            DB::beginTransaction();
            try {

                $this->create([
                    'refer_code' => $refer_code,
                    'refer_table' => $refer_table,
                    'credit_type' => $method,
                    'amount' => $amount,
                    'bonus' => 0,
                    'total' => $amount,
                    'balance_before' => 0,
                    'balance_after' => 0,
                    'credit' => 0,
                    'credit_bonus' => 0,
                    'credit_total' => 0,
                    'credit_before' => $member->balance,
                    'credit_after' => ($member->balance - $amount),
                    'member_code' => $member_code,
                    'gameuser_code' => $user_code,
                    'game_code' => $game_code,
                    'user_name' => $member->user_name,
                    'kind' => $kind,
                    'auto' => 'N',
                    'remark' => $remark,
                    'emp_code' => $emp_code,
                    'ip' => $ip,
                    'user_create' => $emp_name,
                    'user_update' => $emp_name,
                ]);

                app('Gametech\Payment\Repositories\BillRepository')->create([
                    'complete' => 'Y',
                    'enable' => 'Y',
                    'refer_code' => $refer_code,
                    'refer_table' => $refer_table,
                    'ref_id' => '',
                    'credit_before' => $member->balance,
                    'credit_after' => ($member->balance - $amount),
                    'member_code' => $member_code,
                    'game_code' => $game_code,
                    'gameuser_code' => $user_code,
                    'pro_code' => 0,
                    'pro_name' => '',
                    'remark' => $remark,
                    'method' => $kind,
                    'transfer_type' => 2,
                    'amount' => $amount,
                    'balance_before' => $member->balance,
                    'balance_after' => ($member->balance - $amount),
                    'credit' => $amount,
                    'credit_bonus' => 0,
                    'credit_balance' => $amount,
                    'amount_request' => 0,
                    'amount_limit' => 0,
                    'ip' => $ip,
                    'user_create' => $member['name'],
                    'user_update' => $member['name'],
                ]);

                $member->balance -= $amount;
                $member->save();

                $game_user->balance = $member->balance;
                $game_user->save();

                //                DB::commit();

            } catch (Throwable $e) {
                report($e);

                return false;
            }

        }

        Notification::send($member, new RealTimeNotification(Lang::get('app.home.adjust_balance')));

        return true;
    }

    public function tranBonus_(array $data, $id): bool
    {

        $ip = request()->ip();
        $credit_balance = 0;
        $member_code = $data['member_code'];

        $member = $this->memberRepository->find($member_code);
        if (! $member) {
            return false;
        }
        if ($member->credit <= 0) {
            return false;
        }

        $game_user = $this->gameUserRepository->findOneWhere(['member_code' => $member->code, 'enable' => 'Y']);
        if (! $game_user) {
            return false;
        }

        //        DB::beginTransaction();
        try {

            $this->create([
                'refer_code' => 0,
                'refer_table' => '',
                'credit_type' => 'D',
                'amount' => $member->credit,
                'bonus' => 0,
                'total' => $member->credit,
                'balance_before' => $member->balance,
                'balance_after' => ($member->balance + $member->credit),
                'credit' => 0,
                'credit_bonus' => 0,
                'credit_total' => 0,
                'credit_before' => 0,
                'credit_after' => 0,
                'member_code' => $member_code,
                'user_name' => $member->user_name,
                'kind' => 'TRANBONUS',
                'auto' => 'N',
                'remark' => 'โยกโบนัสเข้า กระเป๋าหลัก',
                'emp_code' => 0,
                'ip' => $ip,
                'user_create' => $member->name,
                'user_update' => $member->name,
            ]);

            $member->balance += $member->credit;
            //            $game_user->balance += $member->credit;
            $member->credit -= $member->credit;

            $member->save();
            //            $game_user->save();

            //            DB::commit();

        } catch (Throwable $e) {
            //            DB::rollBack();
            report($e);

            return false;
        }

        return true;
    }

    public function tranBonus(array $data, $id): bool
    {


        $config = core()->getConfigData();
        $ip = request()->ip();
        $credit_balance = 0;
        $member_code = $data['member_code'];

        $member = $this->memberRepository->find($member_code);
        //        dd($member);
        if (! $member) {
            return false;
        }

        if ($id == 'BONUS') {
            if ($member->bonus <= 0) {
                return false;
            }
            $pro_name = 'วงล้อมหาสนุก';
            $amount = $member->bonus;
            $kind = 'TRANBONUS';
            $msg = 'รับโบนัสวงล้อ เข้ากระเป๋า (โยกเข้าเกม)';
            $member->bonus = 0;
            $member->save();
        } elseif ($id == 'FASTSTART') {
            if ($member->faststart <= 0) {
                return false;
            }
            $pro_name = 'ค่าแนะนำ';
            $amount = $member->faststart;
            $kind = 'TRANFT';
            $msg = 'รับค่าแนะนำ เข้ากระเป๋า (โยกเข้าเกม)';
            $member->faststart = 0;
            $member->save();
        } elseif ($id == 'CASHBACK') {
            if ($member->cashback <= 0) {
                return false;
            }
            $pro_name = 'Cashback';
            $amount = $member->cashback;
            $kind = 'TRANCB';
            $msg = 'รับ Cashback เข้ากระเป๋า (โยกเข้าเกม)';
            $member->cashback = 0;
            $member->save();
        } elseif ($id == 'IC') {
            if ($member->ic <= 0) {
                return false;
            }
            $pro_name = 'ยอดเสียเพื่อน';
            $amount = $member->ic;
            $kind = 'TRANIC';
            $msg = 'รับ IC เข้ากระเป๋า (โยกเข้าเกม)';
            $member->ic = 0;
            $member->save();
        } elseif ($id == 'SPIN') {
            if ($member->credit <= 0) {
                return false;
            }
            $amount = $member->credit;
            $kind = 'TRANBONUS';
            $msg = 'รับ โบนัสวงล้อ เข้ากระเป๋า (โยกเข้าเกม)';
            $member->credit = 0;
            $member->save();
        }

        $game = core()->getGame();

        $game_event = $this->gameUserEventRepository->findOneWhere(['method' => $id, 'member_code' => $member->code, 'game_code' => $game->code, 'enable' => 'Y']);
        if (! $game_event) {
            return false;
        }

        $game_user = $this->gameUserRepository->findOneWhere(['member_code' => $member->code, 'game_code' => $game->code, 'enable' => 'Y']);
        if (! $game_user) {
            return false;
        }

        if ($config->seamless == 'Y') {
            if ($member->balance > $config->pro_reset) {
                return false;
            }
        } else {
            if ($game_user->balance > $config->pro_reset) {
                return false;
            }
        }
        //        dd($game_event);

        $game_code = $game->code;
        $user_name = $game_user->user_name;

        $money_text = 'จำนวนเงิน '.$amount;

        if ($config->seamless == 'N') {
            if ($config->multigame_open == 'N') {
                if ($config->freecredit_open == 'Y') {
                    $response = $this->gameUserRepository->UserDeposit($game_code, $user_name, $amount, false);
                    //                    dd($response);
                    if ($response['success'] === true) {
                        ActivityLoggerUser::activity('ฝากเงิน '.$id.' เข้าเกม'.$game->name, $money_text.' ระบบทำการฝากเงินเข้าเกมแล้ว');

                    } else {
                        ActivityLoggerUser::activity('ฝากเงิน '.$id.' เข้าเกม'.$game->name, $money_text.' ไม่สามารถฝากเงินเข้าเกมได้');

                        return false;
                    }
                } else {
                    $response = $this->gameUserRepository->UserDeposit($game_code, $user_name, $amount, false);
                    //                    dd($response);
                    if ($response['success'] === true) {
                        ActivityLoggerUser::activity('ฝากเงิน '.$id.' เข้าเกม'.$game->name, $money_text.' ระบบทำการฝากเงินเข้าเกมแล้ว');

                    } else {
                        ActivityLoggerUser::activity('ฝากเงิน '.$id.' เข้าเกม'.$game->name, $money_text.' ไม่สามารถฝากเงินเข้าเกมได้');

                        return false;
                    }

                }
            }
        } else {
            $response['before'] = $member->balance;
            $response['after'] = ($member->balance + $amount);
            $response['ref_id'] = '';
        }

        $total = ($response['before'] + $amount);
        $promotion = $this->promotionRepository->findOneWhere(['code' => $game_event->pro_code]);
        if ($promotion) {
            $turnpro = $promotion->turnpro;
            $withdraw_limit_rate = $promotion->withdraw_limit_rate;
            if ($turnpro > 0) {
                $amount_total = $response['before'] + ($amount * $promotion->turnpro);
            } else {
                $amount_total = 0;
            }
            //            $amount_total = $response['before'] + ($amount * $promotion->turnpro);
            if ($withdraw_limit_rate > 0) {
                $withdraw_limit_amount = $response['before'] + ($amount * $promotion->withdraw_limit_rate);
            } else {
                $withdraw_limit_amount = 0;
            }
        } else {
            //            $amount_total = $response['before'] + ($amount * $game_event->turnpro);
            //            $withdraw_limit_amount = $response['before'] + ($amount * $game_event->withdraw_limit_rate);
            $turnpro = $game_event->turnpro;
            $withdraw_limit_rate = $game_event->withdraw_limit_rate;
            if ($turnpro > 0) {
                $amount_total = $response['before'] + ($amount * $game_event->turnpro);
            } else {
                $amount_total = 0;
            }

            if ($withdraw_limit_rate > 0) {
                $withdraw_limit_amount = $response['before'] + ($amount * $game_event->withdraw_limit_rate);
            } else {
                $withdraw_limit_amount = 0;
            }
        }

        //        dd('here');
        //        DB::beginTransaction();
        try {

            $bill = $this->create([
                'refer_code' => 0,
                'refer_table' => '',
                'credit_type' => 'D',
                'pro_code' => $game_event->pro_code,
                'pro_name' => $pro_name,
                'amount' => 0,
                'bonus' => $amount,
                'total' => $amount,
                'balance_before' => $response['before'],
                'balance_after' => $response['after'],
                'credit' => 0,
                'credit_bonus' => 0,
                'credit_total' => 0,
                'credit_before' => $response['before'],
                'credit_after' => $response['after'],
                'member_code' => $member_code,
                'gameuser_code' => $game_user->code,
                'game_code' => $game_code,
                'user_name' => $member->user_name,
                'kind' => $kind,
                'auto' => 'N',
                'remark' => 'RefID : '.$response['ref_id'].' '.$msg,
                'emp_code' => 0,
                'ip' => $ip,
                'amount_balance' => $amount_total,
                'withdraw_limit' => $game_event->withdraw_limit,
                'withdraw_limit_amount' => $withdraw_limit_amount,
                'user_create' => $member->name,
                'user_update' => $member->name,
            ]);

            app('Gametech\Payment\Repositories\BillRepository')->create([
                'complete' => 'Y',
                'enable' => 'Y',
                'refer_code' => $bill->code,
                'refer_table' => 'members_credit_log',
                'ref_id' => $response['ref_id'],
                'credit_before' => $response['before'],
                'credit_after' => $response['after'],
                'member_code' => $member_code,
                'game_code' => $game_code,
                'gameuser_code' => $game_user->code,
                'pro_code' => $game_event->pro_code,
                'pro_name' => $pro_name,
                'remark' => $msg,
                'method' => 'BONUS',
                'transfer_type' => 1,
                'amount' => $amount,
                'balance_before' => $response['before'],
                'balance_after' => $response['after'],
                'credit' => 0,
                'credit_bonus' => $amount,
                'credit_balance' => $amount,
                'amount_request' => $amount_total,
                'amount_limit' => $withdraw_limit_amount,
                'ip' => $ip,
                'user_create' => $member['name'],
                'user_update' => $member['name'],
            ]);

            $member->balance = $response['after'];
            //            $game_user->balance += $member->credit;
            //            $member->credit -= $member->credit;

            $member->save();

            $game_user->balance = $response['after'];
            $game_user->pro_code = $game_event->pro_code;
            $game_user->bill_code = $game_event->bill_code;
            $game_user->amount = $game_event->amount;
            $game_user->bonus = $game_event->bonus;
            $game_user->turnpro = $turnpro;
            $game_user->amount_balance = $amount_total;
            $game_user->withdraw_limit = $game_event->withdraw_limit;
            $game_user->withdraw_limit_rate = $withdraw_limit_rate;
            $game_user->withdraw_limit_amount = $withdraw_limit_amount;
            $game_user->save();

            $game_event->bonus = 0;
            $game_event->turnpro = 0;
            $game_event->amount_balance = 0;
            $game_event->withdraw_limit = 0;
            $game_event->withdraw_limit_rate = 0;
            $game_event->withdraw_limit_amount = 0;
            $game_event->save();

            //            DB::commit();

        } catch (Throwable $e) {
            //            DB::rollBack();
            report($e);

            return false;
        }

        return true;
    }
}
