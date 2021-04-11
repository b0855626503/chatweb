<?php

namespace Gametech\Member\Providers;

use Gametech\Member\Models\Member;
use Gametech\Member\Models\MemberCashback;
use Gametech\Member\Models\MemberCreditLog;
use Gametech\Member\Models\MemberDiamondLog;
use Gametech\Member\Models\MemberFreeCredit;
use Gametech\Member\Models\MemberIc;
use Gametech\Member\Models\MemberLog;
use Gametech\Member\Models\MemberPointLog;
use Gametech\Member\Models\MemberPromotionLog;
use Gametech\Member\Models\MemberRewardLog;
use Gametech\Member\Models\MemberSatang;
use Konekt\Concord\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        Member::class,
        MemberCashback::class,
        MemberCreditLog::class,
        MemberLog::class,
        MemberFreeCredit::class,
        MemberPointLog::class,
        MemberDiamondLog::class,
        MemberIc::class,
        MemberSatang::class,
        MemberRewardLog::class,
        MemberPromotionLog::class,
    ];
}
