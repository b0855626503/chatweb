<?php

namespace Gametech\Admin\Http\Controllers;

use App\Http\Controllers\AppBaseController;
use Gametech\Admin\DataTables\RpAllLogDataTable;
use Gametech\Admin\DataTables\RpBillDataTable;
use Gametech\Admin\DataTables\RpBillFreeDataTable;
use Gametech\Admin\DataTables\RpBillTurnDataTable;
use Gametech\Admin\DataTables\RpCashbackDataTable;
use Gametech\Admin\DataTables\RpCreditDataTable;
use Gametech\Admin\DataTables\RpDepositDataTable;
use Gametech\Admin\DataTables\RpMemberIcDataTable;
use Gametech\Admin\DataTables\RpMemberOnlineDataTable;
use Gametech\Admin\DataTables\RpSetDiamondDataTable;
use Gametech\Admin\DataTables\RpSetPointDataTable;
use Gametech\Admin\DataTables\RpSpinDataTable;
use Gametech\Admin\DataTables\RpSponsorDataTable;
use Gametech\Admin\DataTables\RpStaffLogDataTable;
use Gametech\Admin\DataTables\RpSumGameDataTable;
use Gametech\Admin\DataTables\RpSumPaymentDataTable;
use Gametech\Admin\DataTables\RpSumStatDataTable;
use Gametech\Admin\DataTables\RpTopProDataTable;
use Gametech\Admin\DataTables\RpUserLogDataTable;
use Gametech\Admin\DataTables\RpWalletDataTable;
use Gametech\Admin\DataTables\RpWithdrawDataTable;
use Gametech\Admin\DataTables\RpWithdrawFreeDataTable;
use Gametech\Game\Repositories\GameRepository;
use Gametech\Payment\Repositories\BankRepository;
use Gametech\Promotion\Repositories\PromotionRepository;
use Illuminate\Http\Request;
use Mozammil\Censor\Replacers\StarReplacer;


class ReportController extends AppBaseController
{
    protected $_config;

    protected $gameRepository;

    protected $bankRepository;

    protected $promotionRepository;

    public function __construct(
        GameRepository $gameRepo,
        PromotionRepository $promotionRepo,
        BankRepository $bankRepository
    )
    {
        $this->_config = request('_config');

        $this->middleware('admin');

        $this->gameRepository = $gameRepo;
        $this->promotionRepository = $promotionRepo;
        $this->bankRepository = $bankRepository;
    }

    public function rp_log_cashback(RpWalletDataTable $rpWalletDataTable)
    {
        return $rpWalletDataTable->render($this->_config['view']);
    }

    public function rp_log_ic(RpWalletDataTable $rpWalletDataTable)
    {
        return $rpWalletDataTable->render($this->_config['view']);
    }

    public function rp_wallet(RpWalletDataTable $rpWalletDataTable)
    {
        return $rpWalletDataTable->render($this->_config['view']);
    }

    public function rp_setpoint(RpSetPointDataTable $rpSetPointDataTable)
    {
        return $rpSetPointDataTable->render($this->_config['view']);
    }

    public function rp_setdiamond(RpSetDiamondDataTable $rpSetDiamondDataTable)
    {
        return $rpSetDiamondDataTable->render($this->_config['view']);
    }

    public function rp_credit(RpCreditDataTable $rpCreditDataTable)
    {
        return $rpCreditDataTable->render($this->_config['view']);
    }

    public function rp_bill(RpBillDataTable $rpBillDataTable)
    {
        $games = $this->gameRepository->findWhere(['enable' => 'Y'])->pluck('name', 'code');
        $pros = $this->promotionRepository->findWhere(['use_wallet' => 'Y'])->whereIn('code', [1, 2, 4])->pluck('name_th', 'code');
        return $rpBillDataTable->render($this->_config['view'], ['games' => $games, 'pros' => $pros]);
    }

    public function rp_top_promotion(RpTopProDataTable $rpTopProDataTable)
    {
        $pros = $this->promotionRepository->findWhere(['use_wallet' => 'Y'])->whereIn('code', [1, 2, 4, 6])->pluck('name_th', 'code');
        return $rpTopProDataTable->render($this->_config['view'], ['pros' => $pros]);
    }

    public function rp_online_behavior(RpMemberOnlineDataTable $rpMemberOnlineDataTable)
    {
        return $rpMemberOnlineDataTable->render($this->_config['view']);
    }

    public function rp_user_log(RpUserLogDataTable $rpUserLogDataTable)
    {
        return $rpUserLogDataTable->render($this->_config['view']);
    }

    public function rp_staff_log(RpStaffLogDataTable $rpStaffLogDataTable)
    {
        return $rpStaffLogDataTable->render($this->_config['view']);
    }

    public function rp_alllog(RpAllLogDataTable $rpAllLogDataTable)
    {
        $games = $this->gameRepository->findWhere(['enable' => 'Y'])->pluck('name', 'code');

        return $rpAllLogDataTable->render($this->_config['view'], ['games' => $games]);
    }

    public function rp_sum_game(RpSumGameDataTable $rpSumGameDataTable)
    {
        return $rpSumGameDataTable->render($this->_config['view']);
    }

    public function rp_sum_stat(RpSumStatDataTable $rpSumStatDataTable)
    {
        return $rpSumStatDataTable->render($this->_config['view']);
    }

    public function rp_sum_payment(RpSumPaymentDataTable $rpSumPaymentDataTable)
    {
        return $rpSumPaymentDataTable->render($this->_config['view']);
    }

    public function rp_bill_free(RpBillFreeDataTable $rpBillFreeDataTable)
    {
        $games = $this->gameRepository->findWhere(['enable' => 'Y'])->pluck('name', 'code');
        $pros = $this->promotionRepository->findWhere(['use_wallet' => 'Y'])->pluck('name_th', 'code');
        return $rpBillFreeDataTable->render($this->_config['view'], ['games' => $games, 'pros' => $pros]);
    }

    public function rp_withdraw(RpWithdrawDataTable $rpWithdrawDataTable)
    {
        return $rpWithdrawDataTable->render($this->_config['view']);
    }

    public function rp_billturn(RpBillTurnDataTable $rpBillTurnDataTable)
    {
        $games = $this->gameRepository->findWhere(['enable' => 'Y'])->pluck('name', 'code');
        return $rpBillTurnDataTable->render($this->_config['view'], ['games' => $games]);
    }

    public function rp_cashback(RpCashbackDataTable $rpCashbackDataTable)
    {

        return $rpCashbackDataTable->render($this->_config['view']);
    }

    public function rp_member_ic(RpMemberIcDataTable $rpMemberIcDataTable)
    {

        return $rpMemberIcDataTable->render($this->_config['view']);
    }

    public function rp_withdraw_free(RpWithdrawFreeDataTable $rpWithdrawFreeDataTable)
    {
        return $rpWithdrawFreeDataTable->render($this->_config['view']);
    }

    public function rp_sponsor(RpSponsorDataTable $rpSponsorDataTable)
    {
        return $rpSponsorDataTable->render($this->_config['view']);
    }

    public function rp_spin(RpSpinDataTable $rpSpinDataTable)
    {
        $bonus = collect(app('Gametech\Payment\Repositories\BonusSpinRepository')->orderBy('bonus_name')->select('bonus_name')->distinct()->get()->toArray())->map(function ($items) {
            $item = (object)$items;
            return [
                'code' => $item->bonus_name,
                'name' => $item->bonus_name
            ];

        })->pluck('name', 'code');
        return $rpSpinDataTable->render($this->_config['view'], ['bonus' => $bonus]);
    }

    public function rp_deposit(RpDepositDataTable $rpDepositDataTable)
    {
        $banks = collect(app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountInAlls()->toArray())->map(function ($item) {

            return [
                'code' => $item['code'],
                'name' => $item['bank']['shortcode'] . ' [' . $item['acc_no'] . ']'
            ];

        })->pluck('name', 'code');

//        dd($banks);

//        dd($banks);
//        $pros = $this->promotionRepository->findWhere(['use_wallet' => 'Y'])->pluck('name_th', 'code');
        return $rpDepositDataTable->render($this->_config['view'], ['banks' => $banks]);
    }

    public function loadData(Request $request)
    {
        $id = $request->input('id');
        $method = $request->input('method');
        $topic = '';
        $responses = [];

        $stat = app('Gametech\Core\Repositories\DailyStatRepository')->find($id);

        switch ($method){
            case 'new':
                $topic = 'รายชื่อสมาชิกใหม่';
                $member_code = json_decode($stat->member_new_list);
                $responses = app('Gametech\Member\Repositories\MemberRepository')->findWhereIn('code',$member_code);
                break;

            case 'newrefill':
                $topic = 'รายชื่อสมาชิกใหม่ที่เติมเงิน';
                $member_code = json_decode($stat->member_new_refill_list);
                $responses = app('Gametech\Member\Repositories\MemberRepository')->findWhereIn('code',$member_code);
                break;
        }


        $permiss = bouncer()->hasPermission('wallet.member.tel');


        $responses = $responses->map(function ($items,$key) use ($permiss) {
            $item = (object)$items;

            $censor = new StarReplacer();

            if ($permiss) {
                $tel = $item->tel;
            } else {
                $tel = $censor->replace($item->tel);
            }

            return [
                'no' => ++$key,
                'firstname' => $item->firstname,
                'lastname' => $item->lastname,
                'user_name' => $item->user_name,
                'tel' => $tel
            ];

        });

        $result['caption'] = $topic;
        $result['list'] = $responses;

        return $this->sendResponseNew($result,'ดำเนินการเสร็จสิ้น');

    }

}
