<?php

namespace Gametech\Core\Providers;

use Gametech\Core\Models\AllLog;
use Gametech\Core\Models\Announce;
use Gametech\Core\Models\BatchUser;
use Gametech\Core\Models\BillLast;
use Gametech\Core\Models\Config;
use Gametech\Core\Models\DailyStat;
use Gametech\Core\Models\Faq;
use Gametech\Core\Models\Log;
use Gametech\Core\Models\LogType;
use Gametech\Core\Models\Refer;
use Gametech\Core\Models\Reward;
use Gametech\Core\Models\Spin;
use Konekt\Concord\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        AllLog::class,
        Config::class,
        Faq::class,
        Log::class,
        LogType::class,
        Refer::class,
        Spin::class,
        BatchUser::class,
        Announce::class,
        BillLast::class,
        Reward::class,
        DailyStat::class,
    ];
}
