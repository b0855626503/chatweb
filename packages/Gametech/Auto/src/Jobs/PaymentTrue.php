<?php

namespace Gametech\Auto\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;


class PaymentTrue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;

    public $tries = 1;

    public $maxExceptions = 5;

    public $retryAfter = 70;

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


        $mobile_number = $this->id;

        $data = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOne('tw', $mobile_number);


        $datenow = now()->toDateTimeString();
        $date = now()->format('Y_m_d');




        $url = [
            'https://dumbo.168csn.com/tw/Transaction_' . $mobile_number . '_' . $date . '.json',
            'https://sv1.168csn.com/tw/Transaction_' . $mobile_number . '_' . $date . '.json',
            'https://thaislot2.168csn.com/tw/Transaction_' . $mobile_number . '_' . $date . '.json',
        ];


        $success = false;
        foreach ($url as $file) {
            $response = Http::get($file);

            if ($response->successful()) {
                $response = $response->json();
                $success = true;
                break;
            }
        }


        $path = storage_path('logs/tw/Transaction_' . $mobile_number . '_' . now()->format('Y_m_d') . '.log');
        file_put_contents($path, print_r($response, true));

        if ($success) {


            $data->balance = $response['current_balance'];
            $data->checktime = $datenow;
            $data->save();

            $lists = $response['activities'];
            if ($lists) {

                try {

                    foreach ($lists as $value) {
                        if (!isset($value['transaction_reference_id'])) continue;
                        $str = $value['date_time'];
                        $arr = explode(" ", $str);
                        $dtmp = explode('/', $arr[0]);
                        $dates = '20' . $dtmp[2] . '-' . $dtmp[1] . '-' . $dtmp[0] . ' ' . $arr[1] . ':00';

                        $amount = Str::of($value['amount'])->replace('+', '');
                        $amount = Str::of($amount)->replace(',', '')->__toString();
                        if ($value['transaction_reference_id']) {
                            $detail = Str::of($value['transaction_reference_id'])->replace('-', '')->__toString();
                        } else {
                            $detail = '';
                        }


                        $newpayment = app('Gametech\Payment\Repositories\BankPaymentRepository')->firstOrNew(['report_id' => $value['report_id'], 'account_code' => $data->code]);
                        $newpayment->bank = 'twl_' . $mobile_number;
                        $newpayment->bankstatus = 1;
                        $newpayment->bank_time = $dates;
                        $newpayment->type = $value['type'];
                        $newpayment->title = $value['title'];
                        $newpayment->value = $amount;
                        $newpayment->value = $amount;
                        $newpayment->detail = $detail;
                        $newpayment->time = $dates;
                        $newpayment->create_by = 'SYSAUTO';
                        $newpayment->save();
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
