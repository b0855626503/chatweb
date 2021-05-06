<?php

namespace Gametech\Auto\Jobs;


use Gametech\Payment\Models\BankPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;


class PaymentTrue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    public $tries = 1;

    public $maxExceptions = 5;

    public $retryAfter = 130;

    protected $id;


    public function __construct($id)
    {
        $this->id = $id;
    }


    public function handle()
    {
        $header = [];
        $response = [];
        $mobile_number = $this->id;
        $update = true;

        $data = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOne('tw', $mobile_number);


        $datenow = now()->toDateTimeString();
        $date = now()->format('Y_m_d');


        $url = [
            'https://dumbo.168csn.com/tw/Transaction_' . $mobile_number . '_' . $date . '.json',
            'https://sv1.168csn.com/tw/Transaction_' . $mobile_number . '_' . $date . '.json',
            'https://thaislot.168csn.com/tw/Transaction_' . $mobile_number . '_' . $date . '.json',
            'https://thaislot2.168csn.com/tw/Transaction_' . $mobile_number . '_' . $date . '.json',
        ];


        $success = false;
        foreach ((array)$url as $file) {
            $response = Http::timeout(10)->get($file);

            if ($response->successful()) {
//                $header = $response->headers();
                $response = $response->json();
                $success = true;
                break;
            }
        }

//        if (!empty($header['Last-Modified'][0])) {
//            $timestamp = Carbon::parse($header['Last-Modified'][0])->timestamp;
//            if (!Cache::has('tw_' . $mobile_number)) {
//                Cache::put('tw_' . $mobile_number, $timestamp);
//            } else {
//                $cache_timestamp = Cache::get('tw_' . $mobile_number);
//                if ($timestamp == $cache_timestamp) {
//                    $update = false;
//                } else {
//                    Cache::put('tw_' . $mobile_number, $timestamp);
//                }
//            }
//        }


        $path = storage_path('logs/tw/Transaction_' . $mobile_number . '_' . now()->format('Y_m_d') . '.log');
        file_put_contents($path, print_r($response, true));


        if ($success) {

            $data->balance = $response['current_balance'];
            $data->checktime = $datenow;
            $data->save();

//            if ($update) {

            $lists = $response['activities'];

            if (count($lists) > 0) {

                foreach ($lists as $value) {

                    if (empty($value['transaction_reference_id'])) continue;


                    $str = $value['date_time'];
                    $arr = explode(" ", $str);
                    $dtmp = explode('/', $arr[0]);
                    $dates = '20' . $dtmp[2] . '-' . $dtmp[1] . '-' . $dtmp[0] . ' ' . $arr[1] . ':00';

                    $amount = Str::of($value['amount'])->replace('+', '');
                    $amount = Str::of($amount)->replace(',', '')->__toString();

                    $detail = Str::of($value['transaction_reference_id'])->replace('-', '')->__toString();

                    $hash = md5($data->code . $dates . $amount . $detail);


                    $newpayment = BankPayment::firstOrNew(['tx_hash' => $hash, 'account_code' => $data->code]);
                    $newpayment->account_code = $data->code;
                    $newpayment->bank = 'twl_' . $mobile_number;
                    $newpayment->bankstatus = 1;
                    $newpayment->report_id = $value['report_id'];
                    $newpayment->bank_time = $dates;
                    $newpayment->type = $value['type'];
                    $newpayment->title = $value['title'];
                    $newpayment->value = $amount;
                    $newpayment->tx_hash = $hash;
                    $newpayment->detail = $detail;
                    $newpayment->time = $dates;
                    $newpayment->create_by = 'SYSAUTO';
                    $newpayment->save();

                }
            }
//            }
        }
    }

    public function failed(Throwable $exception)
    {
        report($exception);
    }
}
