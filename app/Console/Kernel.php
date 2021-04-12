<?php

namespace App\Console;

use Gametech\Auto\Console\Commands\Cashback;
use Gametech\Auto\Console\Commands\CheckFastStart;
use Gametech\Auto\Console\Commands\CheckPayment;
use Gametech\Auto\Console\Commands\DailyStat;
use Gametech\Auto\Console\Commands\GetPayment;
use Gametech\Auto\Console\Commands\GetPaymentAcc;
use Gametech\Auto\Console\Commands\MemberIC;
use Gametech\Auto\Console\Commands\TopupPayment;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        CheckPayment::class,
        TopupPayment::class,
        GetPayment::class,
        GetPaymentAcc::class,
        Cashback::class,
        MemberIC::class,
        DailyStat::class,
        CheckFastStart::class,
    ];


    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $yesterday = now()->subDays(1)->toDateString();

        $schedule->command('cashback:list')->dailyAt('00:20')->runInBackground()
            ->onFailure(function ($message) {
                Log::warning($message);
            });

        $schedule->command('ic:list')->dailyAt('00:30')->runInBackground()
            ->onFailure(function ($message) {
                Log::warning($message);
            });

        $schedule->command('dailystat:check')->dailyAt('00:05')
            ->onFailure(function ($message) {
                Log::warning($message);
            });
        $schedule->command('dailystat:check ' . $yesterday)->dailyAt('00:10')
            ->onFailure(function ($message) {
                Log::warning($message);
            });


        $schedule->command('payment:get tw')->everyMinute();
        $schedule->command('payment:check kbank 100')->everyMinute();
        $schedule->command('payment:check scb 100')->everyMinute();
        $schedule->command('payment:check bay 100')->everyMinute();
        $schedule->command('payment:check tw 100')->everyMinute();
        $schedule->command('payment:emp-topup 100')->everyMinute();
        sleep(20);
        $schedule->command('payment:check kbank 100')->everyMinute();
        $schedule->command('payment:check scb 100')->everyMinute();
        $schedule->command('payment:check bay 100')->everyMinute();
        $schedule->command('payment:check tw 100')->everyMinute();
        $schedule->command('payment:emp-topup 100')->everyMinute();
        sleep(20);
        $schedule->command('payment:check kbank 100')->everyMinute();
        $schedule->command('payment:check scb 100')->everyMinute();
        $schedule->command('payment:check bay 100')->everyMinute();
        $schedule->command('payment:check tw 100')->everyMinute();
        $schedule->command('payment:emp-topup 100')->everyMinute();

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        $this->load(__DIR__ . '/../../packages/Gametech/Auto/src/Console/Commands');

        require base_path('routes/console.php');
    }
}
