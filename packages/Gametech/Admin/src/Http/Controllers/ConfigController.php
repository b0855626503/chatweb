<?php

namespace Gametech\Admin\Http\Controllers;


use Gametech\Core\Repositories\ConfigRepository;
use Illuminate\Http\Request;

class ConfigController extends AppBaseController
{
    protected $_config;

    protected $repository;

    public function __construct(ConfigRepository $repository)
    {
        $this->_config = request('_config');

        $this->middleware('admin');

        $this->repository = $repository;
    }


    public function index()
    {
        $configs = $this->repository->findOrFail(1);
        $configs = collect($configs)->toArray();

        return view($this->_config['view'])->with('configs' , $configs);
    }

    public function loadData(Request $request)
    {
        $id = $request->input('id');

        $data = $this->repository->findOrFail($id);
        if(!$data){
            return $this->sendError('ไม่พบข้อมูลดังกล่าว',200);
        }

        return $this->sendResponse($data,'ดำเนินการเสร็จสิ้น');

    }

    public function update($id,Request $request)
    {

        $chk = $this->repository->findOrFail($id);

        if (empty($chk)) {
//            session()->flash('error', 'ไม่พบข้อมูลดังกล่าว');
//            return redirect()->back();
            return $this->sendError('ไม่พบข้อมูลดังกล่าว',200);
        }

        $data = $request->all();
        unset($data['fileupload']);
        unset($data['fileuploadnew']);
//        unset($data->fileupload);

        $this->repository->updatenew($data, $id);


//        session()->flash('success', 'บันทึกข้อมูลสำเร็จ');
//        return redirect()->route('admin.announces.index');
        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function getrule(Request $request)
    {
        $id =   $id = $request->input('id');
        $responses = collect($this->memberRemarkRepository->loadRemark($id));

        $responses = $responses->map(function ($items){
            $item = (object)$items;

            return [
                'date_create' =>  core()->formatDate($item->date_create,'d/m/y H:i:s'),
                'remark' => $item->remark,
                'emp_code' => (is_null($item->emp) ? '' : $item->emp->user_name),
                'action' => '<button type="button" class="btn btn-warning btn-xs icon-only" onclick="delSub('.$item->code.')"><i class="fa fa-times"></i></button>'

            ];

        });

        $result['list'] = $responses;

        return $this->sendResponseNew($result, 'complete');
    }


}
