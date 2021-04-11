<?php

namespace Gametech\Admin\Http\Controllers;

use Gametech\Admin\DataTables\MemberfreeDataTable;
use Gametech\Game\Repositories\GameRepository;
use Gametech\Game\Repositories\GameUserFreeRepository;
use Gametech\Member\Repositories\MemberFreeCreditRepository;
use Gametech\Member\Repositories\MemberPointLogRepository;
use Gametech\Member\Repositories\MemberRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Str;


class MemberfreeController extends AppBaseController
{
    protected $_config;

    protected $gameRepository;

    protected $gameUserRepository;

    protected $memberRepository;

    protected $memberCreditLogRepository;

    protected $memberPointLogRepository;

    /**
     * MemberController constructor.
     * @param GameUserFreeRepository $gameUserRepository
     * @param GameRepository $gameRepository
     * @param MemberRepository $memberRepository
     * @param MemberFreeCreditRepository $memberCreditLogRepository
     * @param MemberPointLogRepository $memberPointLogRepository
     */
    public function __construct
    (
        GameUserFreeRepository $gameUserRepository,
        GameRepository $gameRepository,
        MemberRepository $memberRepository,
        MemberFreeCreditRepository $memberCreditLogRepository,
        MemberPointLogRepository $memberPointLogRepository
    )

    {
        $this->_config = request('_config');

        $this->middleware('admin');

        $this->gameUserRepository = $gameUserRepository;

        $this->gameRepository = $gameRepository;

        $this->memberRepository = $memberRepository;

        $this->memberCreditLogRepository = $memberCreditLogRepository;

        $this->memberPointLogRepository = $memberPointLogRepository;
    }


    public function index(MemberfreeDataTable $memberfreeDataTable)
    {
        return $memberfreeDataTable->render($this->_config['view']);
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

        $types = ['D' => 'เพิ่ม Credit' , 'W' => 'ลด Credit'];

        $config = core()->getConfigData();

        $member = $this->memberRepository->find($id);

        if ($amount < 1) {
            return $this->sendError('ยอดเงินไม่ถูกต้อง',200);
        } elseif ($amount > $config['maxsetcredit']) {
            return $this->sendError('ไม่สามารถทำรายการเกินครั้งละ '.core()->currency($config['maxsetcredit']),200);
        } elseif ($method == 'W' && ($member->balance_free - $amount) < 0) {
            return $this->sendError('ยอดเงินหลังทำรายการ ไม่สามารถติดลบได้',200);
        }

        $data = [
            'kind' => 'SETCREDIT',
            'remark' => $remark,
            'amount' => $amount,
            'method' => $method,
            'member_code' => $id,
            'emp_code' => $this->id(),
            'emp_name' => $this->user()->name.' '.$this->user()->surname
        ];

        $response = $this->memberCreditLogRepository->setCredit($data);
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

        $member = $this->memberRepository->find($id);
        $responses = [];

        switch ($method){
            case 'gameuser':
                $responses = $this->gameuser($id);
                break;

            case 'transfer':
                $responses = $this->gametransfer($id);
                break;

            case 'deposit':
                $responses = $this->gamedeposit($id);
                break;

            case 'withdraw':
                $responses = $this->gamewithdraw($id);
                break;

            case 'setwallet':
                $responses = $this->gamesetwallet($id);
                break;

            case 'setpoint':
                $responses = $this->gamesetpoint($id);
                break;
        }


        $result['name'] = $member->firstname.' '.$member->lastname;
        $result['list'] = $responses;

        return $this->sendResponseNew($result,'complete');
    }

    public function gameuser($id)
    {

        $games = collect($this->gameRepository->getGameUserFreeById($id)->toArray())->whereNotNull('game_user_free');

        $games = $games->map(function ($items){
            $item = (object)$items;
            return [
                'game' => $item->name,
                'user_name' => $item->game_user_free['user_name'],
                'balance' => $item->game_user_free['balance'],
            ];

        });

        return $games->values()->all();
    }

    public function gamesetwallet($id)
    {

        $responses = collect(app('Gametech\Member\Repositories\MemberFreeCreditRepository')->orderBy('date_create','desc')->findWhere(['member_code' => $id , 'kind' => 'SETCREDIT' , 'enable' => 'Y'])->toArray());

        $responses = $responses->map(function ($items){
            $item = (object)$items;
            return [
                'date_create' =>  core()->formatDate($item->date_create,'d/m/y H:i'),
                'credit_amount' => $item->credit_amount,
                'credit_before' => $item->credit_before,
                'credit_balance' => $item->credit_balance,
                'remark' => $item->remark,
                'credit_type' => $item->credit_type == 'D' ? '<span class="text-success">เพิ่ม Credit</span>' : '<span class="text-danger">ลด Credit</span>',
            ];

        });

        return $responses->take(10)->values()->all();
    }

    public function gametransfer($id)
    {

        $responses = collect(app('Gametech\Member\Repositories\MemberRepository')->loadBillFree($id,'','')->toArray());

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

        return $responses->take(10)->values()->all();
    }

    public function gamewithdraw($id)
    {

        $responses = collect(app('Gametech\Member\Repositories\MemberRepository')->loadWithdrawFree($id,'','')->toArray());

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

        return $responses->take(10)->values()->all();
    }

    public function edit(Request $request)
    {
        $user = $this->user()->name.' '.$this->user()->surname;
        $id = $request->input('id');
        $status = $request->input('status');
        $method = $request->input('method');


        $data[$method] = $status;

        $chk = $this->memberRepository->find($id);
        if(!$chk){
            return $this->sendError('ไม่พบข้อมูลดังกล่าว',200);
        }

        $data['user_update'] = $user;
        $this->memberRepository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }


}
