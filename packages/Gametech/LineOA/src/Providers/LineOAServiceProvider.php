<?php

namespace Gametech\LineOA\Providers;

use Illuminate\Support\ServiceProvider;

class LineOAServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerConfig();
        //        foreach (glob(dirname(__DIR__).'/Config/*.php') as $file) {
        //            //            Log::debug($file);
        //            $name = pathinfo($file, PATHINFO_FILENAME);
        //            $this->mergeConfigFrom($file, $name);
        //        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__.'/../Routes/webhook.php');

        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');

        $this->loadViewsFrom(__DIR__.'/../Resources/views/admin', 'admin');

    }

    /**
     * Register package config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/admin-menu.php', 'menu.admin'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/acl.php', 'acl'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/http_timeout.php', 'line_oa'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/services.php', 'services'
        );
    }
}
