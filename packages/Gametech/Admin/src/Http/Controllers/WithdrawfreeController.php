<?php

namespace Gametech\Admin\Http\Controllers;


use Gametech\Admin\DataTables\WithdrawfreeDataTable;
use Gametech\Member\Repositories\MemberFreeCreditRepository;
use Gametech\Payment\Repositories\WithdrawFreeRepository;
use Illuminate\Http\Request;


class WithdrawfreeController extends AppBaseController
{
    protected $_config;

    protected $repository;

    protected $memberFreeCreditRepository;

    public function __construct
    (
        WithdrawFreeRepository  $repository,
        MemberFreeCreditRepository $memberFreeCreditRepo
    )
    {
        $this->_config = request('_config');

        $this->middleware('admin');

        $this->repository = $repository;

        $this->memberFreeCreditRepository = $memberFreeCreditRepo;
    }


    public function index(WithdrawfreeDataTable $withdrawfreeDataTable)
    {
        return $withdrawfreeDataTable->render($this->_config['view']);
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
            'remark' => 'คืนยอดจากการถอน',
            'kind' => 'ROLLBACK',
            'amount' => $chk->amount,
            'method' => 'D',
            'member_code' => $chk->member_code,
            'emp_code' => $this->id(),
            'emp_name' => $this->user()->name.' '.$this->user()->surname
        ];

        $response = $this->memberFreeCreditRepository->setCredit($datanew);

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


}
