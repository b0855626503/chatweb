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

//    public $deleteWhenMissingModels = true;

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

        $payment = BankPayment::find($this->item);

        if ($payment->status == 0 && $payment->autocheck == 'W') {

            $logs = Alllog::where('bank_payment_id', $payment->code)->first();

            if (!empty($logs)) {
                $payment->autocheck = 'Y';
                $payment->status = 1;
                $payment->save();
            } else {
                app('Gametech\Payment\Repositories\PaymentPromotionRepository')->checkFastStart($payment->value, $payment->member_topup, $payment->code);
                app('Gametech\Payment\Repositories\BankPaymentRepository')->refillPayment(collect($payment)->toArray());
            }

        }
    }

    public function failed(Throwable $exception)
    {
        report($exception);
    }
}
