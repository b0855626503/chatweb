<?php

namespace Gametech\FacebookOA\Http\Controllers\Admin;

use Gametech\Admin\Http\Controllers\AppBaseController;
use Gametech\FacebookOA\DataTables\FacebookAccountDataTable;
use Gametech\FacebookOA\Models\FacebookAccount;
use Gametech\FacebookOA\Repositories\FacebookAccountRepository;
use Gametech\Member\Repositories\MemberRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        $data = (array) $request->input('data', []);

        // ====== Default / Normalize บาง field ======

        // ถ้าไม่ส่ง status มา ให้ default เป็น active
        if (empty($data['status'])) {
            $data['status'] = 'active';
        }

        // default language ถ้าไม่ส่งมา
        if (empty($data['default_outgoing_language'])) {
            $data['default_outgoing_language'] = 'th';
        }

        if (empty($data['default_incoming_language'])) {
            $data['default_incoming_language'] = 'th';
        }

        // default timezone ถ้าไม่ระบุ
        if (empty($data['timezone'])) {
            $data['timezone'] = 'Asia/Bangkok';
        }

        // ถ้าไม่ได้กรอก webhook_verify_token จากฟอร์ม ให้ระบบตั้งให้เอง
        if (empty($data['webhook_verify_token'])) {
            $data['webhook_verify_token'] = 'fb_oa_'.Str::random(24);
        }

        // สร้าง webhook_token แบบ unique สำหรับใช้ใน URL /api/facebook-oa/webhook/{token}
        if (empty($data['webhook_token'])) {
            $data['webhook_token'] = $this->generateUniqueWebhookToken();
        }

        $data['user_create'] = $user;
        $data['user_update'] = $user;

        $this->repository->create($data);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    public function update(Request $request)
    {
        $user = $this->user()->name.' '.$this->user()->surname;
        $id = $request->input('id');
        $data = (array) $request->input('data', []);

        $chk = $this->repository->find($id);
        if (! $chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        // กันเคสเผลอล้าง status ทิ้ง
        if (empty($data['status'])) {
            $data['status'] = $chk->status ?? 'active';
        }

        // ถ้าไม่ระบุ language ให้ใช้ค่าของเดิม
        if (! array_key_exists('default_outgoing_language', $data)) {
            $data['default_outgoing_language'] = $chk->default_outgoing_language ?? 'th';
        }

        if (! array_key_exists('default_incoming_language', $data)) {
            $data['default_incoming_language'] = $chk->default_incoming_language ?? 'th';
        }

        // timezone ถ้าไม่ส่งมา ให้ใช้ค่าของเดิม
        if (! array_key_exists('timezone', $data)) {
            $data['timezone'] = $chk->timezone ?? 'Asia/Bangkok';
        }

        // webhook_verify_token ถ้าไม่ส่งมา ปล่อยใช้ของเดิม (ไม่ regenerate ให้เอง)
        if (! array_key_exists('webhook_verify_token', $data)) {
            $data['webhook_verify_token'] = $chk->webhook_verify_token;
        }

        // โดยปกติเราไม่เปลี่ยน webhook_token อัตโนมัติ
        // แต่ถ้า front-end ส่งมาก็ให้ update ตามนั้น (เช่น ทำปุ่ม regenerate เองในอนาคต)
        if (! array_key_exists('webhook_token', $data)) {
            $data['webhook_token'] = $chk->webhook_token;
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

        $data = [
            $method => $status,
        ];

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

    /**
     * สร้าง webhook_token ที่ไม่ซ้ำในตาราง facebook_accounts
     */
    protected function generateUniqueWebhookToken(): string
    {
        do {
            $token = Str::random(32);
        } while (
            FacebookAccount::query()
                ->where('webhook_token', $token)
                ->exists()
        );

        return $token;
    }
}
