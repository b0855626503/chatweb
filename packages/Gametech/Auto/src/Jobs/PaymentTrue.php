<?php

namespace Gametech\Auto\Jobs;

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

    public function __construct
    (
        $id
    )
    {
        $this->id = $id;
    }


    public function handle(): bool
    {
        $timestamp = 0;
        $header = [];
        $response = [];
        $mobile_number = $this->id;

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
                $header = $response->headers();
                $response = $response->json();
                $success = true;
                break;
            }
        }

        if (isset($header['Last-Modified'][0])) {
            $timestamp = Carbon::parse($header['Last-Modified'][0])->timestamp;
            if (!Cache::has('tw_' . $mobile_number)) {
                Cache::put('tw_' . $mobile_number, $timestamp);
            } else {
                $cache_timestamp = Cache::get('tw_' . $mobile_number);
                if ($timestamp == $cache_timestamp) {
                    return true;
                } else {
                    Cache::put('tw_' . $mobile_number, $timestamp);
                }
            }
        }


        $path = storage_path('logs/tw/Transaction_' . $mobile_number . '_' . now()->format('Y_m_d') . '.log');
        file_put_contents($path, print_r($response, true));


        if ($success) {

            $data->balance = $response['current_balance'];
            $data->checktime = $datenow;
            $data->save();

            $lists = $response['activities'];
            if (count($lists) > 0) {

                try {

                    foreach ($lists as $value) {

                        if ($value['original_type'] == 'p2p') {
                            if (empty($value['transaction_reference_id'])) continue;

                        } elseif ($value['original_type'] == 'transfer') {
                            $value['transaction_reference_id'] = $value['sub_title'];
                            if (empty($value['transaction_reference_id'])) continue;

                        } else {
                            continue;
                        }


                        $str = $value['date_time'];
                        $arr = explode(" ", $str);
                        $dtmp = explode('/', $arr[0]);
                        $dates = '20' . $dtmp[2] . '-' . $dtmp[1] . '-' . $dtmp[0] . ' ' . $arr[1] . ':00';

                        $amount = Str::of($value['amount'])->replace('+', '');
                        $amount = Str::of($amount)->replace(',', '')->__toString();

                        $detail = Str::of($value['transaction_reference_id'])->replace('-', '')->__toString();

                        $hash = md5($data->code . $dates . $amount . $detail);


                        $chk = app('Gametech\Payment\Repositories\BankPaymentRepository')->findOneWhere(['tx_hash' => $hash]);

                        if (!$chk) {

                            $chk = app('Gametech\Payment\Repositories\BankPaymentRepository')->findOneWhere(['report_id' => $value['report_id'], 'account_code' => $data->code]);

                            if (!$chk) {
                                $newpayment = app('Gametech\Payment\Repositories\BankPaymentRepository')->firstOrNew(['bank_time' => $dates, 'account_code' => $data->code, 'value' => $amount, 'detail' => $detail]);
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
                    }


                } catch (Throwable $e) {
                    report($e);
                    return false;
                }

            }

        }

        return true;

    }

    public function failed(Throwable $exception): bool
    {
        report($exception);
        return false;
    }
}
