<?php

namespace Gametech\Auto\Jobs;




use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PaymentBay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $list;

    protected $data;


    public function __construct($list,$data)
    {
        $this->list = $list;
        $this->data = $data;
    }


    public function handle()
    {
        $list =  $this->list;
        $data =  $this->data;

        $list['value'] = str_replace(",", "",number_format($list['value'],2));
        $list['tx_hash'] = md5($list['date'] . $list['detail'] . $list['value'] . $list['channel']);

        $chk = app('Gametech\Payment\Repositories\BankPaymentRepository')->findWhere(['bank' => 'bay_' . $data['acc_no'], 'bank_time' => $list['date'], 'detail' => $list['detail'], 'channel' => $list['channel'], 'value' => $list['value']]);
        if ($chk->exists()) {
            return false;
        }

        $report_id = time().random_int(0,1000);
        $response = app('Gametech\Payment\Repositories\BankPaymentRepository')->create([
            'bank' => 'bay_'.$data['acc_no'],
            'report_id' => $report_id,
            'account_code' => $data['bankcode'],
            'bankstatus' => 1,
            'bankname' => 'BAY',
            'status' => 0,
            'bank_time' => $list['date'],
            'detail' => $list['detail'],
            'value' => $list['value'],
            'channel' => $list['channel'],
            'tx_hash' => $list['tx_hash'],
            'atranferer' => $list['acc_num'],
            'create_by' => 'SYSAUTO',
            'user_create' => '',
            'user_update' => ''
        ]);

        if($response->code){
            return true;
        }else{
            return false;
        }

    }
}
