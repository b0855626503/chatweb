<?php

namespace Gametech\Auto\Jobs;

use Gametech\Core\Models\AllLog;
use Gametech\Payment\Models\BankPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;


class TopupPayments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;

    public $tries = 0;

    public $maxExceptions = 3;

    protected $bankpayment;

    protected $alllog;

    protected $item;

    public function __construct(
        BankPayment $bankpayment,
        AllLog $alllog
    )
    {

        $this->bankpayment = $bankpayment->withoutRelations();
        $this->alllog = $alllog->withoutRelations();

    }


    public function handle($item)
    {
        $this->item = $item;

        $payment = $this->bankpayment->where('code', $this->item)->where('status', 0)->where('autocheck', 'W')->firstOrFail();

        if (empty($payment)) {
            return false;
        }


        $logs = $this->alllog->where('bank_payment_id', $payment->code);
        if ($logs->exists()) {

            $payment->autocheck = 'Y';
            $payment->status = 1;
            $payment->save();

            return false;
        }

        app('Gametech\Payment\Repositories\PaymentPromotionRepository')->checkFastStart($payment->amount, $payment->member_topup, $payment->code);
        return app('Gametech\Payment\Repositories\BankPaymentRepository')->refillPayment(collect($payment)->toArray());

    }
}
