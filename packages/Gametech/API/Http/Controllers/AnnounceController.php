<?php

namespace Gametech\API\Http\Controllers;

use Gametech\Core\Repositories\AnnounceRepository;
use Illuminate\Http\Request;

class AnnounceController extends AppBaseController
{
    protected $_config;

    protected $repository;

    public function __construct(AnnounceRepository $repository)
    {
        $this->_config = request('_config');

        $this->middleware('api');

        $this->repository = $repository;
    }


    public function Announce(Request $request)
    {
        $id = 1;
        $chk = $this->repository->findOrFail($id);

        if (empty($chk)) {
            return $this->sendError('ไม่สามารถบันทึกข้อมูลได้',200);
        }

        $data['content'] = $request->input('message');
        $data['new'] = 'Y';

        $this->repository->update($data, $id);

        return $this->sendSuccess('บันทึกข้อมูลแล้ว');

    }


}
