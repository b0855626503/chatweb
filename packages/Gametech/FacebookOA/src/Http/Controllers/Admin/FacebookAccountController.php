<?php

namespace Gametech\FacebookOA\Http\Controllers\Admin;

use Gametech\FacebookOA\DataTables\FacebookAccountDataTable;
use Gametech\Admin\Http\Controllers\AppBaseController;
use Gametech\FacebookOA\Repositories\FacebookAccountRepository;
use Gametech\Member\Repositories\MemberRepository;
use Illuminate\Http\Request;

class FacebookAccountController extends AppBaseController
{
    protected $_config;

    protected $repository;

    protected $memberRepository;

    public function __construct(
        FacebookAccountRepository $repository,

        MemberRepository $memberRepository
    ) {
        $this->_config = request('_config');

        $this->middleware('admin');

        $this->repository = $repository;

        $this->memberRepository = $memberRepository;
    }

    public function index(FacebookAccountDataTable $facebookAccountDataTable)
    {
        return $facebookAccountDataTable->render($this->_config['view']);
    }

    public function loadData(Request $request)
    {
        $id = $request->input('id');

        $data = $this->repository->find($id);
        if (! $data) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        return $this->sendResponse($data, 'ดำเนินการเสร็จสิ้น');

    }

    public function create(Request $request)
    {
        $user = $this->user()->name.' '.$this->user()->surname;
        $data = $request->input('data');

        $data['user_create'] = $user;
        $data['user_update'] = $user;

        $this->repository->create($data);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function update(Request $request)
    {
        $user = $this->user()->name.' '.$this->user()->surname;
        $id = $request->input('id');
        $data = $request->input('data');

        $chk = $this->repository->find($id);
        if (! $chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        $data['user_update'] = $user;
        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function edit(Request $request)
    {
        $user = $this->user()->name.' '.$this->user()->surname;
        $id = $request->input('id');
        $status = $request->input('status');
        $method = $request->input('method');

        $data[$method] = $status;

        $chk = $this->repository->find($id);
        if (! $chk) {
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

        if (! $chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        $this->repository->delete($id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }
}
