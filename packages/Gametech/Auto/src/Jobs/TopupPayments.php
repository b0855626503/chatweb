<?php

namespace Gametech\Auto\Jobs;


use Gametech\Core\Models\AllLog;
use Gametech\Payment\Models\BankPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;


class TopupPayments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $deleteWhenMissingModels = true;

    public $timeout = 60;

    public $tries = 0;

    public $maxExceptions = 3;

    protected $bankpayment;

    protected $alllog;

    protected $item;



    public function __construct($item)
    {
        $this->item = $item;


    }


    public function handle()
    {


        $payment = BankPayment::where('code', $this->item)->where('status', 0)->where('autocheck', 'W')->first();

        if (empty($payment)) {
            return false;
        }


        $logs = Alllog::where('bank_payment_id', $payment->code);
        if ($logs->exists()) {

            $payment->autocheck = 'Y';
            $payment->status = 1;
            $payment->save();

            return false;
        }

        app('Gametech\Payment\Repositories\PaymentPromotionRepository')->checkFastStart($payment->value, $payment->member_topup, $payment->code);
        return app('Gametech\Payment\Repositories\BankPaymentRepository')->refillPayment(collect($payment)->toArray());

    }

    public function failed(Throwable $exception)
    {
        report($exception);
    }
}
