<?php

namespace Gametech\Auto\Console\Commands;

use Gametech\Auto\Jobs\PaymentBay;
use Gametech\Auto\Jobs\PaymentKbank;
use Gametech\Auto\Jobs\PaymentKtb;
use Gametech\Auto\Jobs\PaymentTrue;
use Illuminate\Console\Command;

class GetPaymentAcc extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:get-account {bank} {account}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Transaction and insert to Bank Payment By Bank Account';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->bankPaymentRepository = app('Gametech\Payment\Repositories\BankPaymentRepository');
        $this->memberRepository = app('Gametech\Member\Repositories\MemberRepository');
        $this->allLogRepository = app('Gametech\Core\Repositories\AllLogRepository');
        $this->paymentPromotionRepository = app('Gametech\Payment\Repositories\PaymentPromotionRepository');
        $this->bankAccountRepository = app('Gametech\Payment\Repositories\BankAccountRepository');
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $id = $this->argument('bank');
        $account = $this->argument('account');

        $this->info('Start get Transaction by : ' . $id . ' , Account No : ' . $account);
        switch ($id) {
            case 'tw':
                PaymentTrue::dispatch($account)->onQueue('payment');
                break;
            case 'kbank':
                $topup = new PaymentKbank($account);
                PaymentKbank::dispatch($topup)->onQueue('payment');
                break;
            case 'bay':
                $topup = new PaymentBay($account);
                PaymentBay::dispatch($topup)->onQueue('payment');
                break;
            case 'ktb':
                $topup = new PaymentKtb($account);
                PaymentKtb::dispatch($topup)->onQueue('payment');
                break;
        }

        return true;

    }
}
