<?php

namespace Gametech\Admin\Http\Controllers;


use Gametech\Admin\DataTables\MemberDataTable;
use Gametech\Game\Repositories\GameRepository;
use Gametech\Game\Repositories\GameUserRepository;
use Gametech\Member\Repositories\MemberCreditLogRepository;
use Gametech\Member\Repositories\MemberDiamondLogRepository;
use Gametech\Member\Repositories\MemberPointLogRepository;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\BankPaymentRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;


class MemberController extends AppBaseController
{
    private $_config;

    private $gameRepository;

    private $bankPaymentRepository;

    private $gameUserRepository;

    private $memberRepository;

    private $memberCreditLogRepository;

    private $memberPointLogRepository;

    private $memberDiamondLogRepository;

    /**
     * MemberController constructor.
     * @param GameUserRepository $gameUserRepo
     * @param GameRepository $gameRepo
     * @param MemberRepository $memberRepo
     * @param MemberCreditLogRepository $memberCreditLogRepo
     * @param MemberPointLogRepository $memberPointLogRepo
     * @param MemberDiamondLogRepository $memberDiamondLogRepo
     * @param BankPaymentRepository $bankPaymentRepo
     */
    public function __construct
    (
        GameUserRepository $gameUserRepo,
        GameRepository $gameRepo,
        MemberRepository $memberRepo,
        MemberCreditLogRepository $memberCreditLogRepo,
        MemberPointLogRepository $memberPointLogRepo,
        MemberDiamondLogRepository $memberDiamondLogRepo,
        BankPaymentRepository $bankPaymentRepo
    )

    {
        $this->_config = request('_config');

        $this->middleware('admin');

        $this->gameUserRepository = $gameUserRepo;

        $this->gameRepository = $gameRepo;

        $this->memberRepository = $memberRepo;

        $this->memberCreditLogRepository = $memberCreditLogRepo;

        $this->memberPointLogRepository = $memberPointLogRepo;

        $this->bankPaymentRepository = $bankPaymentRepo;

        $this->memberDiamondLogRepository = $memberDiamondLogRepo;
    }


    public function index(MemberDataTable $memberDataTable)
    {
        return $memberDataTable->render($this->_config['view']);
    }

    public function edit(Request $request)
    {
        $id = $request->input('id');
        $status = $request->input('status');
        $method = $request->input('method');

        $data[$method] = $status;

        $member = $this->memberRepository->find($id);
        if(!$member){
            return $this->sendError('ไม่พบข้อมูลดังกล่าว',200);
        }

        $member = $this->memberRepository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function destroy(Request $request)
    {
        $id = $request->input('id');

        $chk = $this->memberRepository->find($id);

        if(!$chk){
            return $this->sendError('ไม่พบข้อมูลดังกล่าว',200);
        }

        $this->memberRepository->delete($id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    public function refill(Request $request)
    {
        $return['success'] = false;

        $datenow = now()->toDateTimeString();
        $user = $this->user()->name.' '.$this->user()->surname;
        $ip = $request->ip();

        $request->validate([
            'id' => 'required',
            'amount' => 'required|numeric',
            'account_code' => 'required|integer',
            'remark_admin' => 'required|string'
        ]);

        $id = $request->input('id');
        $amount = $request->input('amount');
        $remark = $request->input('remark_admin');
        $account = $request->input('account_code');


        $member = $this->memberRepository->find($id);

        $bank_account = app('Gametech\Payment\Repositories\BankAccountRepository')->find($account);

        $bank = app('Gametech\Payment\Repositories\BankRepository')->find($bank_account->banks);

        if ($amount < 1) {
            return $this->sendError('ยอดเงินไม่ถูกต้อง',200);
        }

        $data = [
            'bank' => strtolower($bank->shortcode.'_'.$bank_account->acc_no),
            'detail' => 'เพิ่มรายการฝากเงินโดย Staff : '.$user,
            'account_code' => $account,
            'autocheck' => 'W',
            'bankstatus' => 1,
            'bank_name' => $bank->shortcode,
            'bank_time' => $datenow,
            'channel' => 'MANUAL',
            'value' => $amount,
            'status' => 0,
            'ip_admin' => $ip,
            'member_topup' => $id,
            'remark_admin' => $remark,
            'emp_topup' => $this->id(),
            'user_create' => 'รอระบบเติมอัตโนมัติ ทำรายการฝากเงินโดย Staff : '.$user,
            'create_by' => $user
        ];

        $response = $this->bankPaymentRepository->create($data);
        if($response->code){
            return $this->sendSuccess('ดำเนินการ ทำรายการฝากเงิน เรียบร้อยแล้ว');
        }else{
            return $this->sendError('ไม่สามารถทำรายการได้ โปรดลองใหม่อีกครั้ง',200);
        }


    }

    public function setWallet(Request $request)
    {
        $return['success'] = false;

        $request->validate([
            'id' => 'required',
            'amount' => 'required|numeric',
            'type' => 'required|string',
            'remark' => 'required|string'
        ]);

        $id = $request->input('id');
        $amount = $request->input('amount');
        $remark = $request->input('remark');
        $method = $request->input('type');

        $types = ['D' => 'เพิ่ม Wallet' , 'W' => 'ลด Wallet'];

        $config = core()->getConfigData();

        $member = $this->memberRepository->find($id);

        if ($amount < 1) {
            return $this->sendError('ยอดเงินไม่ถูกต้อง',200);
        } elseif ($amount > $config['maxsetcredit']) {
            return $this->sendError('ไม่สามารถทำรายการเกินครั้งละ '.core()->currency($config['maxsetcredit']),200);
        } elseif ($method == 'W' && ($member->balance - $amount) < 0) {
            return $this->sendError('ยอดเงินหลังทำรายการ ไม่สามารถติดลบได้',200);
        }

        $data = [
            'refer_code' => $id,
            'refer_table' => 'members',
            'kind' => 'SETWALLET',
            'remark' => $remark,
            'amount' => $amount,
            'method' => $method,
            'member_code' => $id,
            'emp_code' => $this->id(),
            'emp_name' => $this->user()->name.' '.$this->user()->surname
        ];

        $response = $this->memberCreditLogRepository->setWallet($data);
        if($response){
            return $this->sendSuccess('ดำเนินการ '.$types[$method].' เรียบร้อยแล้ว');
        }else{
            return $this->sendError('ไม่สามารถทำรายการได้ โปรดลองใหม่อีกครั้ง',200);
        }


    }

    public function setPoint(Request $request)
    {
        $return['success'] = false;

        $request->validate([
            'id' => 'required',
            'amount' => 'required|numeric',
            'type' => 'required|string',
            'remark' => 'required|string'
        ]);

        $id = $request->input('id');
        $amount = $request->input('amount');
        $remark = $request->input('remark');
        $method = $request->input('type');

        $types = ['D' => 'เพิ่ม Point' , 'W' => 'ลด Point'];

        $config = core()->getConfigData();

        $member = $this->memberRepository->find($id);

        if ($amount < 1) {
            return $this->sendError('ยอดเงินไม่ถูกต้อง',200);
        } elseif ($method == 'W' && ($member->point_deposit - $amount) < 0) {
            return $this->sendError('ยอดเงินหลังทำรายการ ไม่สามารถติดลบได้',200);
        }

        $data = [
            'remark' => $remark,
            'amount' => $amount,
            'method' => $method,
            'member_code' => $id,
            'emp_code' => $this->id(),
            'emp_name' => $this->user()->name.' '.$this->user()->surname
        ];

        $response = $this->memberPointLogRepository->setPoint($data);
        if($response){
            return $this->sendSuccess('ดำเนินการ '.$types[$method].' เรียบร้อยแล้ว');
        }else{
            return $this->sendError('ไม่สามารถทำรายการได้ โปรดลองใหม่อีกครั้ง',200);
        }


    }

    public function setDiamond(Request $request)
    {
        $return['success'] = false;

        $request->validate([
            'id' => 'required',
            'amount' => 'required|numeric',
            'type' => 'required|string',
            'remark' => 'required|string'
        ]);

        $id = $request->input('id');
        $amount = $request->input('amount');
        $remark = $request->input('remark');
        $method = $request->input('type');

        $types = ['D' => 'เพิ่ม Diamond' , 'W' => 'ลด Diamond'];

        $config = core()->getConfigData();

        $member = $this->memberRepository->find($id);

        if ($amount < 1) {
            return $this->sendError('ยอด Diamond ไม่ถูกต้อง',200);
        } elseif ($method == 'W' && ($member->diamond - $amount) < 0) {
            return $this->sendError('ยอด Diamond หลังทำรายการ ไม่สามารถติดลบได้',200);
        }

        $data = [
            'remark' => $remark,
            'amount' => $amount,
            'method' => $method,
            'member_code' => $id,
            'emp_code' => $this->id(),
            'emp_name' => $this->user()->name.' '.$this->user()->surname
        ];

        $response = $this->memberDiamondLogRepository->setDiamond($data);
        if($response){
            return $this->sendSuccess('ดำเนินการ '.$types[$method].' เรียบร้อยแล้ว');
        }else{
            return $this->sendError('ไม่สามารถทำรายการได้ โปรดลองใหม่อีกครั้ง',200);
        }


    }


    public function gameLog(Request $request)
    {
        $id = $request->input('id');
        $method = $request->input('method');
        $header = '';
        $member = $this->memberRepository->find($id);
        $responses = [];

        switch ($method){
            case 'gameuser':
                $header = 'ข้อมูลบัญชีเกม';
                $responses = $this->gameuser($id);
                break;

            case 'transfer':
                $header = 'ข้อมูลการโยก 50 รายการล่าสุด';
                $responses = $this->gametransfer($id);
                break;

            case 'deposit':
                $header = 'ข้อมูลการฝากเงิน 50 รายการล่าสุด';
                $responses = $this->gamedeposit($id);
                break;

            case 'withdraw':
                $header = 'ข้อมูลการถอน 50 รายการล่าสุด';
                $responses = $this->gamewithdraw($id);
                break;

            case 'setwallet':
                $header = 'ข้อมูลการ Set Wallet 50 รายการล่าสุด';
                $responses = $this->gamesetwallet($id);
                break;

            case 'setpoint':
                $header = 'ข้อมูลการ Set Point 50 รายการล่าสุด';
                $responses = $this->gamesetpoint($id);
                break;

            case 'setdiamond':
                $header = 'ข้อมูลการ Set Diamond 50 รายการล่าสุด';
                $responses = $this->gamesetdiamond($id);
                break;
        }


        $result['name'] = $member->firstname.' '.$member->lastname .'('.$header.')';
        $result['list'] = $responses;

        return $this->sendResponseNew($result,'complete');
    }

    public function gameuser($id)
    {

        $games = collect($this->gameRepository->getGameUserById($id)->toArray())->whereNotNull('game_user');

        $games = $games->map(function ($items){
            $item = (object)$items;
            return [
                'game' => $item->name,
                'user_name' => $item->game_user['user_name'],
                'balance' => $item->game_user['balance'],
                'promotion' => (!is_null($item->game_user['promotion']) ? $item->game_user['promotion']['name_th'] : '-'),
                'turn' => ($item->game_user['pro_code'] > 0 ? $item->game_user['turnpro'] : '-'),
                'amount_balance' => ($item->game_user['pro_code'] > 0 ? $item->game_user['amount_balance'] : '-'),
                'withdraw_limit' => ($item->game_user['pro_code'] > 0 ? ($item->game_user['withdraw_limit'] > 0 ? $item->game_user['withdraw_limit'] : '-') : '-'),
            ];

        });

        return $games->values()->all();
    }

    public function gamesetwallet($id)
    {

        $responses = collect($this->memberCreditLogRepository->orderBy('date_create','desc')->findWhere(['member_code' => $id , 'kind' => 'SETWALLET' , 'enable' => 'Y'])->toArray());

        $responses = $responses->map(function ($items){
            $item = (object)$items;
            return [
                'date_create' =>  core()->formatDate($item->date_create,'d/m/y H:i'),
                'credit_amount' => $item->total,
                'credit_before' => $item->balance_before,
                'credit_balance' => $item->balance_after,
                'remark' => $item->remark,
                'credit_type' => $item->credit_type == 'D' ? '<span class="text-success">เพิ่ม Wallet</span>' : '<span class="text-danger">ลด Wallet</span>',

            ];

        });

        return $responses->take(50)->values()->all();
    }

    public function gamesetpoint($id)
    {

        $responses = collect(app('Gametech\Member\Repositories\MemberPointLogRepository')->orderBy('date_create','desc')->findWhere(['member_code' => $id  , 'enable' => 'Y'])->toArray());

        $responses = $responses->map(function ($items){
            $item = (object)$items;
            return [
                'date_create' =>  core()->formatDate($item->date_create,'d/m/y H:i'),
                'credit_amount' => $item->point_amount,
                'credit_before' => $item->point_before,
                'credit_balance' => $item->point_balance,
                'remark' => $item->remark,
                'credit_type' => $item->point_type == 'D' ? '<span class="text-success">เพิ่ม Point</span>' : '<span class="text-danger">ลด Point</span>',

            ];

        });

        return $responses->take(50)->values()->all();
    }

    public function gamesetdiamond($id)
    {

        $responses = collect(app('Gametech\Member\Repositories\MemberDiamondLogRepository')->orderBy('date_create','desc')->findWhere(['member_code' => $id  , 'enable' => 'Y'])->toArray());

        $responses = $responses->map(function ($items){
            $item = (object)$items;
            return [
                'date_create' =>  core()->formatDate($item->date_create,'d/m/y H:i'),
                'credit_amount' => $item->diamond_amount,
                'credit_before' => $item->diamond_before,
                'credit_balance' => $item->diamond_balance,
                'remark' => $item->remark,
                'credit_type' => $item->diamond_type == 'D' ? '<span class="text-success">เพิ่ม Diamond</span>' : '<span class="text-danger">ลด Diamond</span>',

            ];

        });

        return $responses->take(50)->values()->all();
    }

    public function gametransfer($id)
    {

        $responses = collect($this->memberRepository->loadBill($id,'','')->toArray());

        $responses = $responses->map(function ($items){
            $item = (object)$items;
            return [
                'id' => '#BL'.Str::of($item->code)->padLeft(8,0),
                'date_create' =>  core()->formatDate($item->date_create,'d/m/y H:i'),
                'amount' => $item->amount,
                'game_name' => $item->game['name'],
                'transfer' => $item->transfer_type == 1 ? '<span class="text-success">โยกเข้าเกม</span>' : '<span class="text-danger">โยกออกเกม</span>',
            ];

        });

        return $responses->take(50)->values()->all();
    }

    public function gamedeposit($id)
    {

        $responses = collect($this->memberRepository->loadDeposit($id,'','')->toArray());

        $responses = $responses->map(function ($items){
            $item = (object)$items;

            return [
                'id' => '#DP'.Str::of($item->code)->padLeft(8,0),
                'date_create' =>  core()->formatDate($item->date_create,'d/m/y H:i'),
                'amount' => $item->value,
                'credit_bonus' => $item->pro_amount,
                'credit_before' => $item->before_credit,
                'credit_after' => $item->after_credit
            ];

        });

        return $responses->take(50)->values()->all();
    }

    public function gamewithdraw($id)
    {

        $responses = collect($this->memberRepository->loadWithdraw($id,'','')->toArray());

        $responses = $responses->map(function ($items){
            $item = (object)$items;
            $status = [ '0' => 'รอโอนเงิน' , '1' => 'โอนเงินสำเร็จ' , '2' => 'ยกเลิก'];


            return [
                'id' => '#WD'.Str::of($item->code)->padLeft(8,0),
                'date_create' => core()->formatDate($item->date_create,'d/m/Y H:i'),
                'amount' => $item->amount,
                'credit_before' => $item->oldcredit,
                'credit_after' => $item->aftercredit,
                'status_display' => $status[$item->status]
            ];

        });

        return $responses->take(50)->values()->all();
    }

    public function loadBank()
    {
        $banks = [
            'value' => '',
            'text' => 'ธนาคาร'
        ];

        $responses = collect(app('Gametech\Payment\Repositories\BankRepository')->all()->toArray());

        $responses = $responses->map(function ($items){
            $item = (object)$items;
            return [
                'value' => $item->code,
                'text' => $item->name_th
            ];

        })->prepend($banks);



        $result['banks'] = $responses;
        return $this->sendResponseNew($result,'complete');
    }

    public function loadBankAccount()
    {
        $banks = [
            'value' => '',
            'text' => 'เลือกช่องทางที่ฝาก'
        ];

        $responses = collect(app('Gametech\Payment\Repositories\BankRepository')->getBankInAccount()->toArray());

        $responses = $responses->map(function ($items){
            $item = (object)$items;
            return [
                'value' => $item->bank_account['code'],
                'text' => $item->name_th.' ['.$item->bank_account['acc_no'].']'
            ];

        })->prepend($banks);



        $result['banks'] = $responses;
        return $this->sendResponseNew($result,'complete');
    }

    public function update($id,Request $request)
    {
        $user = $this->user()->name.' '.$this->user()->surname;

        $data = json_decode($request['data'],true);


        $bank_code = $data['bank_code'];
//        $validator = Validator::make($data, [
//            'acc_no' => [
//                'required',
//                'digits_between:1,15',
//                Rule::unique('members', 'acc_no')->ignore($id,'code')->where(function ($query) use ($bank_code) {
//                    return $query->where('bank_code', $bank_code);
//                })
//            ]
//
//        ]);
//        if ($validator->fails()) {
//            $errors = $validator->errors();
//            return $this->sendError($errors->messages(),200);
//        }


        $chk = $this->memberRepository->find($id);
        if(!$chk){
            return $this->sendError('ไม่พบข้อมูลดังกล่าว',200);
        }

        if($data['user_pass']){
            $data['password'] = Hash::make($data['user_pass']);
        }else{
            unset($data['user_pass']);
        }
        $data['name'] = $data['firstname'].' '.$data['lastname'];
        $data['user_update'] = $user;
        $this->memberRepository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function loadData(Request $request)
    {
        $id = $request->input('id');

        $data = $this->memberRepository->find($id);
        if(!$data){
            return $this->sendError('ไม่พบข้อมูลดังกล่าว',200);
        }

        return $this->sendResponse($data,'ดำเนินการเสร็จสิ้น');

    }


}
