<?php

namespace Gametech\Game\Providers;

use Gametech\Game\Models\Game;
use Gametech\Game\Models\GameUser;
use Gametech\Game\Models\GameUserFree;
use Gametech\Game\Models\User;
use Konekt\Concord\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        Game::class,
        GameUser::class,
        GameUserFree::class,
        User::class,
    ];
}
