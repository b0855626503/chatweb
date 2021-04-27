<?php

namespace Gametech\Payment\Providers;

use Konekt\Concord\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        \Gametech\Payment\Models\Bank::class,
        \Gametech\Payment\Models\BankAccount::class,
        \Gametech\Payment\Models\BankPayment::class,
        \Gametech\Payment\Models\Bill::class,
        \Gametech\Payment\Models\BillFree::class,
        \Gametech\Payment\Models\Bonus::class,
        \Gametech\Payment\Models\BonusSpin::class,
        \Gametech\Payment\Models\Payment::class,
        \Gametech\Payment\Models\PaymentLog::class,
        \Gametech\Payment\Models\PaymentLogFree::class,
        \Gametech\Payment\Models\PaymentPromotion::class,
        \Gametech\Payment\Models\PaymentWaiting::class,
        \Gametech\Payment\Models\Withdraw::class,
        \Gametech\Payment\Models\WithdrawFree::class,
        \Gametech\Payment\Models\BankRule::class,
    ];
}
