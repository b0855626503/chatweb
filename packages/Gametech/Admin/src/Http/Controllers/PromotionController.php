<?php

namespace Gametech\Admin\Http\Controllers;

use Gametech\Admin\DataTables\PromotionDataTable;
use Gametech\Promotion\Repositories\PromotionAmountRepository;
use Gametech\Promotion\Repositories\PromotionRepository;
use Gametech\Promotion\Repositories\PromotionTimeRepository;
use Illuminate\Http\Request;


class PromotionController extends AppBaseController
{
    protected $_config;

    protected $repository;

    protected $promotionTimeRepository;

    protected $promotionAmountRepository;

    public function __construct
    (
        PromotionRepository $repository,
        PromotionTimeRepository $promotionTimeRepo,
        PromotionAmountRepository $promotionAmountRepo

    )
    {
        $this->_config = request('_config');

        $this->middleware('admin');

        $this->repository = $repository;

        $this->promotionTimeRepository = $promotionTimeRepo;

        $this->promotionAmountRepository = $promotionAmountRepo;
    }


    public function index(PromotionDataTable $promotionDataTable)
    {
        return $promotionDataTable->render($this->_config['view']);
    }

    public function loadData(Request $request)
    {
        $id = $request->input('id');


        $data = $this->repository->find($id);
        if (!$data) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }


        $table = '';
        switch ($data->length_type) {
            case 'TIME':
            case 'TIMEPC':
                $table = 'promotions_time';
                break;

            case 'AMOUNT':
            case 'AMOUNTPC':
            case 'BETWEEN':
            case 'BETWEENPC':

                $table = 'promotions_amount';
                break;
            default:
                break;
        }

        $data->table = $table;
        return $this->sendResponse($data, 'ดำเนินการเสร็จสิ้น');

    }

    public function create(Request $request)
    {
        $user = $this->user()->name . ' ' . $this->user()->surname;

        $data = json_decode($request['data'], true);



        $data['user_create'] = $user;
        $data['user_update'] = $user;

        $this->repository->createnew($data);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function createsub(Request $request)
    {
        $user = $this->user()->name . ' ' . $this->user()->surname;
        $id = $request->input('id');
        $data = $request->input('data');
        $table = $request->input('table');

        $data['pro_code'] = $id;
        $data['user_create'] = $user;
        $data['user_update'] = $user;

        if($table == 'promotions_amount'){

            $this->promotionAmountRepository->create($data);

        } else if($table == 'promotions_time'){

            $this->promotionTimeRepository->create($data);

        }



        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function update($id, Request $request)
    {
        $user = $this->user()->name . ' ' . $this->user()->surname;

        $data = json_decode($request['data'], true);


        $chk = $this->repository->find($id);
        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        $data['user_update'] = $user;
        $this->repository->updatenew($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function edit(Request $request)
    {
        $user = $this->user()->name . ' ' . $this->user()->surname;
        $id = $request->input('id');
        $status = $request->input('status');
        $method = $request->input('method');


        $data[$method] = $status;

        $chk = $this->repository->find($id);
        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        $data['user_update'] = $user;
        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function destroy(Request $request)
    {
        $id = $request->input('id');

        $chk = $this->repository->find($id);

        if(!$chk){
            return $this->sendError('ไม่พบข้อมูลดังกล่าว',200);
        }

        $this->repository->delete($id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    public function destroysub(Request $request)
    {
        $id = $request->input('id');
        $table = $request->input('method');



        if($table == 'promotions_amount'){

            $chk = $this->promotionAmountRepository->find($id);

            if (!$chk) {
                return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
            }

            $this->promotionAmountRepository->delete($id);

        } else if($table == 'promotions_time'){

            $chk = $this->promotionTimeRepository->find($id);

            if (!$chk) {
                return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
            }
            $this->promotionTimeRepository->delete($id);

        }



        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    public function loadPro(Request $request)
    {
        $id = $request->input('id');
        $method = $request->input('method');
        $table = '';

        $responses = [];


        switch ($method) {
            case 'TIME':
            case 'TIMEPC':
                $responses = $this->promotionTimeRepository->findByField('pro_code', $id);
                $table = 'promotions_time';
                break;

            case 'AMOUNT':
            case 'AMOUNTPC':
            case 'BETWEEN':
            case 'BETWEENPC':
                $responses = $this->promotionAmountRepository->findByField('pro_code', $id);
            $table = 'promotions_amount';
                break;

        }
        if($method === 'TIME' || $method === 'TIMEPC'){
            $no = 0;
            $responses = collect($responses)->map(function ($items) use ($no,$table){
                ++$no;
                $item = (object)$items;
                return [
                    'no' => $item->code,
                    'time_start' => $item->time_start,
                    'time_stop' => $item->time_stop,
                    'deposit_amount' => $item->deposit_amount,
                    'deposit_stop' => $item->deposit_stop,
                    'amount' => $item->amount,
                    'action' => '<button type="button" class="btn btn-warning btn-xs icon-only" onclick="delSub('.$item->code.','. "'" .$table."'" .')"><i class="fa fa-times"></i></button>'

                ];

            });
        }else{
            $no = 0;
            $responses = collect($responses)->map(function ($items) use ($no,$table){
                ++$no;
                $item = (object)$items;
                return [
                    'no' => $item->code,
                    'deposit_amount' => $item->deposit_amount,
                    'deposit_stop' => $item->deposit_stop,
                    'amount' => $item->amount,
                    'action' => '<button type="button" class="btn btn-warning btn-xs icon-only" onclick="delSub('.$item->code.','. "'" .$table."'" .')"><i class="fa fa-times"></i></button>'

                ];

            });
        }


        $result['list'] = $responses;

        return $this->sendResponseNew($result, 'complete');
    }


}
