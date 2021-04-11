<?php

namespace Gametech\Auto\Console\Commands;

use Illuminate\Console\Command;

class GetPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:get {bank}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Transaction and insert to Bank Payment';

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

        $this->info('Get Transaction : ' . $id);
        $banks = $this->bankAccountRepository->getAccount($id);
//        $this->info($banks);

        $bar = $this->output->createProgressBar($banks->count());
        $bar->start();


        foreach ($banks as $i => $payment) {
            $this->call('payment:get-account', [
                'bank' => $id, 'account' => $payment->acc_no
            ]);
            $bar->advance();
        }

        $bar->finish();
    }
}
