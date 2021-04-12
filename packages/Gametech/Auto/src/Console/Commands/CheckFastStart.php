<?php

namespace Gametech\Auto\Console\Commands;


use Illuminate\Console\Command;


class CheckFastStart extends Command
{
    protected $bankPaymentRepository;

    protected $paymentPromotionRepository;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'faststart:bill {code}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and Refill Cashback to member';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->bankPaymentRepository = app('Gametech\Payment\Repositories\BankPaymentRepository');
        $this->paymentPromotionRepository = app('Gametech\Payment\Repositories\PaymentPromotionRepository');

        parent::__construct();

    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $code = $this->argument('code');

        $payment = $this->bankPaymentRepository->findOrFail($code);

        $this->line($payment);


        $result = $this->paymentPromotionRepository->checkFastStart($payment->amount, $payment->member_topup, $payment->code);
        if($result){
            $this->line('Success');
        }else{
            $this->line('Fail');
        }

    }

}
