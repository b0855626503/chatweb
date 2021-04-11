<?php

namespace Gametech\Auto\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;


class TopupPayments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;

    public $tries = 0;

    public $maxExceptions = 3;


    protected $item;

    public function __construct($item)
    {
        $this->item = $item;

    }


    public function handle()
    {


        $payment = DB::table('bank_payment')->where('code', $this->item)->where('status', 0)->where('autocheck', 'W')->first();

        if (!$payment) {

            return false;
        }


        $alllog = DB::table('all_log')->where('bank_payment_id', $payment->code);
        if ($alllog->exists()) {
            DB::table('bank_payment')->where('code', $this->item)->update([
                'autocheck' => 'Y',
                'status' => 1
            ]);
            return false;
        }

        app('Gametech\Payment\Repositories\PaymentPromotionRepository')->checkFastStart($payment->amount, $payment->member_topup, $payment->code);
        return app('Gametech\Payment\Repositories\BankPaymentRepository')->refillPayment(collect($payment)->toArray());

    }
}
