<?php

namespace Gametech\Admin\Http\Controllers;

use Gametech\Admin\DataTables\WithdrawDataTable;
use Gametech\Member\Repositories\MemberCreditLogRepository;
use Gametech\Payment\Repositories\WithdrawRepository;
use Illuminate\Http\Request;


class WithdrawController extends AppBaseController
{
    protected $_config;

    protected $repository;

    protected $memberCreditLogRepository;

    public function __construct
    (
        WithdrawRepository $repository,
        MemberCreditLogRepository $memberCreditLogRepo
    )
    {
        $this->_config = request('_config');

        $this->middleware('admin');

        $this->repository = $repository;

        $this->memberCreditLogRepository = $memberCreditLogRepo;
    }


    public function index(WithdrawDataTable $withdrawDataTable)
    {
        return $withdrawDataTable->render($this->_config['view']);
    }

    public function loadData(Request $request)
    {
        $id = $request->input('id');


        $data = $this->repository->with(['member','bank'])->find($id);

        if (!$data) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }



        return $this->sendResponse($data, 'ดำเนินการเสร็จสิ้น');

    }

    public function update($id,Request $request)
    {
        $ip = $request->ip();
        $user = $this->user()->name.' '.$this->user()->surname;
        $datenow = now()->toDateTimeString();

        $data = json_decode($request['data'],true);


        $chk = $this->repository->find($id);
        if(!$chk){
            return $this->sendSuccess('ไม่พบข้อมูลดังกล่าว');
        }

//        if($chk->emp_topup > 0 || $chk->autocheck == 'W'){
//            return $this->sendSuccess('รายการนี้ กำลัง รอเติมเงินผ่านระบบ Auto อยู่');
//        }


        $data['emp_approve'] = $this->id();
        $data['status'] = 1;
        $data['ip_admin'] = $ip;
        $data['user_update'] = $user;
        $data['date_approve'] = $datenow;
        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function clear(Request $request)
    {
        $user = $this->user()->name.' '.$this->user()->surname;
        $id = $request->input('id');
        $remark = $request->input('remark');

        $chk = $this->repository->find($id);

        if(!$chk){
            return $this->sendError('ไม่พบข้อมูลดังกล่าว',200);
        }

        $datanew = [
            'refer_code' => $id,
            'refer_table' => 'withdraws',
            'remark' => 'คืนยอดจากการถอน',
            'kind' => 'ROLLBACK',
            'amount' => $chk->amount,
            'method' => 'D',
            'member_code' => $chk->member_code,
            'emp_code' => $this->id(),
            'emp_name' => $this->user()->name.' '.$this->user()->surname
        ];

        $response = $this->memberCreditLogRepository->setWallet($datanew);

        if($response){
            $data['ip_admin'] = $request->ip();
            $data['remark_admin'] = $remark;
            $data['status'] = 2;
            $data['emp_approve'] = $this->id();
            $data['user_update'] = $user;
            $data['date_approve'] = now()->toDateTimeString();
            $this->repository->update($data, $id);
        }


        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    public function destroy(Request $request)
    {
        $user = $this->user()->name.' '.$this->user()->surname;
        $id = $request->input('id');

        $chk = $this->repository->find($id);

        if(!$chk){
            return $this->sendError('ไม่พบข้อมูลดังกล่าว',200);
        }


        $data['enable'] = 'N';
        $data['user_update'] = $user;
        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    public function loadBank()
    {
        $banks = [
            'value' => '0',
            'text' => 'ไม่ระบุบัญชี'
        ];

        $responses = collect(app('Gametech\Payment\Repositories\BankRepository')->getBankOutAccount()->toArray());

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

}
