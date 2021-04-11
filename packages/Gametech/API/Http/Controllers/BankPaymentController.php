<?php

namespace Gametech\API\Http\Controllers;

use Gametech\Auto\Jobs\PaymentBay;
use Gametech\Payment\Repositories\BankAccountRepository;
use Gametech\Payment\Repositories\BankPaymentRepository;
use Illuminate\Http\Request;

class BankPaymentController extends AppBaseController
{
    protected $_config;

    protected $repository;

    protected $bankAccount;

    public function __construct(
        BankPaymentRepository $repository,
        BankAccountRepository $bankAccount
    )
    {
        $this->_config = request('_config');

        $this->middleware('api');

        $this->repository = $repository;

        $this->bankAccount = $bankAccount;
    }


    public function krungsri(Request $request)
    {
        $data = json_decode($request['data'], true);

        $path = storage_path('bankpayment/krungsri.log');
        file_put_contents($path, print_r($data, true));

        $bank_account = $this->bankAccount->findOneByField('acc_no', $data['acc_no']);
        if (!$bank_account) {
            return $this->sendError('ไม่พบเลขบัญชี', 200);
        }

        $data['bankcode'] = $bank_account['code'];

        if (isset($data['balance'])) {
            $balance = ($data['balance'] ? $data['balance'] : 0);
            $this->bankAccount->update([
                'balance' => $balance
            ], $bank_account->code);
        }

        $out = $data['data'];
        if (!(empty($out)) && count($out) > 0) {

            for ($indexrow = count($out); $indexrow >= 0; $indexrow--) {

                $list = [
                    'date' => $out[$indexrow]['time'],
                    'channel' => $out[$indexrow]['channel'],
                    'acc_num' => $out[$indexrow]['acc_num'],
                    'detail' => $out[$indexrow]['detail'],
                    'checktime' => strtotime(date("Y-m-d H:i:s")),
                    'value' => str_replace(",", "", $out[$indexrow]['value'])
                ];


                if ($out[$indexrow]['value'] == "" || $out[$indexrow]['value'] == 0) {
                    continue;
                }
                if (strlen($list['date']) < 6) {
                    continue;
                }

                PaymentBay::dispatchAfterResponse($list,$data)->onQueue('payment');

            }
        }

    }


}
