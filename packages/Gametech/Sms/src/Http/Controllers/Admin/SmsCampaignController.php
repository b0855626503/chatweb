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
        $id = (int) $request->input('id');

        $data = $this->repository->find($id);

        if (! $data) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        // === เพิ่ม stats ของ recipients (เพื่อให้ modal ใช้แสดง/แนะนำ dispatch) ===
        // ใช้ query จาก repository เดิม เพื่อไม่ผูกกับ model โดยตรง
        $base = $this->recipientRepository->query()->where('campaign_id', $data->id);

        $recipientsTotal = (clone $base)->count();

        $queuedTotal = (clone $base)->where('status', 'queued')->count();

        // delivered บางระบบอาจใช้ delivered หรือ sent เป็นหลัก
        $deliveredTotal = (clone $base)->whereIn('status', ['delivered'])->count();

        $failedTotal = (clone $base)->whereIn('status', ['failed'])->count();

        // (optional) เอา import_batch_id ล่าสุดไว้โชว์ใน modal
        $lastImportBatchId = (clone $base)
            ->whereNotNull('import_batch_id')
            ->orderByDesc('id')
            ->value('import_batch_id');

        // แปลงเป็น array เพื่อไม่ไปยุ่ง object ของ repository
        $payload = is_object($data) ? $data->toArray() : (array) $data;

        $payload['recipients_total'] = (int) $recipientsTotal;
        $payload['queued_total'] = (int) $queuedTotal;
        $payload['delivered_total'] = (int) $deliveredTotal;
        $payload['failed_total'] = (int) $failedTotal;
        $payload['last_import_batch_id'] = $lastImportBatchId ? (int) $lastImportBatchId : null;

        return $this->sendResponse($payload, 'ดำเนินการเสร็จสิ้น');
    }

    public function create(Request $request)
    {
        $user = $this->user()->name.' '.$this->user()->surname;

        $data = (array) $request->input('data', []);

        $data['provider'] = $data['provider'] ?? 'vonage';
        $data['status'] = $data['status'] ?? 'draft';
        $data['audience_mode'] = $data['audience_mode'] ?? 'member_all';
        $data['respect_opt_out'] = array_key_exists('respect_opt_out', $data) ? (bool) $data['respect_opt_out'] : true;
        $data['require_consent'] = array_key_exists('require_consent', $data) ? (bool) $data['require_consent'] : false;

        $data['user_create'] = $user;
        $data['user_update'] = $user;

        $created = $this->repository->create($data);

        // แนะนำ: ส่ง id กลับไปด้วย (ไม่กระทบของเดิม เพราะยังส่งSuccess เหมือนเดิม)
        // ถ้า frontend ยังไม่ใช้ก็ไม่เป็นไร
        return $this->sendResponse([
            'id' => is_object($created) ? (int) $created->id : null,
        ], 'ดำเนินการเสร็จสิ้น');
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

        try {
            $dispatchedIds = DB::transaction(function () use ($campaign, $limit) {
                // lock แถว queued กันกดซ้ำ/แข่งกัน
                $ids = $this->recipientRepository
                    ->query()
                    ->where('campaign_id', $campaign->id)
                    ->where('status', 'queued')
                    ->orderBy('id')
                    ->limit($limit)
                    ->lockForUpdate()
                    ->pluck('id');

                if ($ids->isEmpty()) {
                    return collect();
                }

                // mark เป็น sending ก่อน dispatch เพื่อกันปล่อยซ้ำ
                $this->recipientRepository
                    ->query()
                    ->whereIn('id', $ids->all())
                    ->update([
                        'status' => 'sending',
                        'queued_at' => DB::raw('IFNULL(queued_at, NOW())'),
                    ]);

                return $ids;
            });

            foreach ($dispatchedIds as $rid) {
                SendSmsJob::dispatch((int) $rid)->onQueue('sms');
            }

            if (($campaign->status ?? 'draft') === 'draft') {
                $this->repository->update([
                    'status' => 'running',
                    'started_at' => $campaign->started_at ?: now(),
                ], $campaign->id);
            }

            return $this->sendResponse([
                'dispatched' => $dispatchedIds->count(),
            ], 'ดำเนินการเสร็จสิ้น');

        } catch (\Throwable $e) {
            return $this->sendError('Dispatch ไม่สำเร็จ: '.$e->getMessage(), 200);
        }
    }
}
