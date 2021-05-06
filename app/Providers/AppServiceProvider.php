<?php

namespace App\Providers;

use Gametech\Core\Tree;


use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{


    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app['request']->server->set('HTTPS', true);

        $this->registerConfig();

    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
        URL::forceScheme('https');
        JsonResource::withoutWrapping();

        DB::listen(function($query) {
            Log::debug($query->sql, $query->bindings, $query->time);
        });

//        Queue::looping(function () {
//            while (DB::transactionLevel() > 0) {
//                DB::rollBack();
//            }
//        });


        view()->composer(['wallet::layouts.*', 'wallet::customer.*'], function ($view) {
            $tree = Tree::create();
            $config = core()->getConfigData();
            $view->with('config', $config);
            $view->with('menu', $tree);
        });

        view()->composer(['admin::layouts.*'], function ($view) {

            $config = core()->getConfigData();
            $view->with('config', $config);

        });
    }

    protected function registerConfig()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__) . '/../game/game.php', 'game'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__) . '/../game/gamefree.php', 'gamefree'
        );


    }
}
