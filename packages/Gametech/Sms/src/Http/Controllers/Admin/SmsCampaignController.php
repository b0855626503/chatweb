<?php

namespace Gametech\Sms\Http\Controllers\Admin;

use Gametech\Admin\Http\Controllers\AppBaseController;
use Gametech\Sms\DataTables\SmsCampaignDataTable;
use Gametech\Sms\Jobs\SendSmsJob;
use Gametech\Sms\Repositories\SmsCampaignRepository;
use Gametech\Sms\Repositories\SmsImportBatchRepository;
use Gametech\Sms\Repositories\SmsRecipientRepository;
use Gametech\Sms\Services\Recipients\SmsRecipientBuilderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SmsCampaignController extends AppBaseController
{
    protected $_config;

    protected $repository;

    protected $importBatchRepository;

    protected $recipientRepository;

    protected $recipientBuilder;

    public function __construct(
        SmsCampaignRepository $repository,
        SmsImportBatchRepository $importBatchRepository,
        SmsRecipientRepository $recipientRepository,
        SmsRecipientBuilderService $recipientBuilder
    ) {
        $this->_config = request('_config');

        $this->middleware('admin');

        $this->repository = $repository;
        $this->importBatchRepository = $importBatchRepository;
        $this->recipientRepository = $recipientRepository;
        $this->recipientBuilder = $recipientBuilder;
    }

    public function index(SmsCampaignDataTable $dataTable)
    {
        return $dataTable->render($this->_config['view']);
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

        // ปรับค่า default ที่ควรมีสำหรับ campaign
        $data['provider'] = $data['provider'] ?? 'vonage';
        $data['status'] = $data['status'] ?? 'draft';
        $data['audience_mode'] = $data['audience_mode'] ?? 'member_all';
        $data['respect_opt_out'] = array_key_exists('respect_opt_out', $data) ? (bool) $data['respect_opt_out'] : true;
        $data['require_consent'] = array_key_exists('require_consent', $data) ? (bool) $data['require_consent'] : false;

        // user audit (ให้ตรงแพตเทิร์นเดิม)
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

        $data['user_update'] = $user;

        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    /**
     * Toggle/edit single field pattern (เหมือน controller เดิม)
     * request: id, status, method
     * example: method=status, status=paused
     */
    public function edit(Request $request)
    {
        $user = $this->user()->name.' '.$this->user()->surname;

        $id = $request->input('id');
        $status = $request->input('status');
        $method = $request->input('method');

        if (! $method) {
            return $this->sendError('method ไม่ถูกต้อง', 200);
        }

        $data = [];
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
        $id = $request->input('id') ?: $request->route('id');

        $chk = $this->repository->find($id);

        if (! $chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        $this->repository->delete($id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    /**
     * Build recipients into sms_recipients for a campaign
     *
     * request:
     * - id (campaign id)
     * - mode: member_all | upload_only | mixed
     * - import_batch_id (required if upload_only/mixed)
     */
    public function buildRecipients(Request $request)
    {
        $id = (int) $request->input('id');
        $mode = (string) $request->input('mode', 'member_all');
        $importBatchId = $request->input('import_batch_id');

        $campaign = $this->repository->find($id);

        if (! $campaign) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        $result = [];

        try {
            DB::beginTransaction();

            if ($mode === 'member_all' || $mode === 'mixed') {
                $result['members'] = $this->recipientBuilder->buildFromMembers($campaign);
            }

            if ($mode === 'upload_only' || $mode === 'mixed') {
                if (! $importBatchId) {
                    DB::rollBack();

                    return $this->sendError('กรุณาเลือก import_batch_id', 200);
                }

                $batch = $this->importBatchRepository->find((int) $importBatchId);

                if (! $batch) {
                    DB::rollBack();

                    return $this->sendError('ไม่พบ import batch ดังกล่าว', 200);
                }

                // phones ชั่วคราวถูกเก็บใน meta (ตามโค้ดที่ให้ไปก่อนหน้า)
                $phones = (array) ($batch->meta['phones'] ?? []);

                $result['upload'] = $this->recipientBuilder->buildFromImportBatch(
                    $campaign,
                    $batch,
                    $phones,
                    (string) ($batch->country_code ?: '66')
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->sendError('เกิดข้อผิดพลาด: '.$e->getMessage(), 200);
        }

        return $this->sendResponse($result, 'ดำเนินการเสร็จสิ้น');
    }

    /**
     * Dispatch queued recipients to SendSmsJob
     *
     * request:
     * - id (campaign id)
     * - limit (default 1000, max 5000)
     */
    public function dispatchQueued(Request $request)
    {
        $id = (int) $request->input('id');
        $limit = (int) $request->input('limit', 1000);

        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 5000) {
            $limit = 5000;
        }

        $campaign = $this->repository->find($id);

        if (! $campaign) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        $recipientIds = $this->recipientRepository
            ->query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'queued')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($recipientIds as $rid) {
            SendSmsJob::dispatch((int) $rid)->onQueue('sms');
        }

        // อัปเดตสถานะแคมเปญแบบเบา ๆ
        if (($campaign->status ?? 'draft') === 'draft') {
            $this->repository->update([
                'status' => 'running',
                'started_at' => $campaign->started_at ?: now(),
            ], $campaign->id);
        }

        return $this->sendResponse([
            'dispatched' => $recipientIds->count(),
        ], 'ดำเนินการเสร็จสิ้น');
    }
}
