<?php

namespace Gametech\Core\Providers;

use Gametech\Core\Core;
use Gametech\Core\Exceptions\Handler;
use Gametech\Core\Models\ConfigProxy;
use Gametech\Core\Observers\ConfigObserver;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        ConfigProxy::observe(ConfigObserver::class);

        // $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'core');
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            ExceptionHandler::class,
            Handler::class
        );

        $this->app->register(EventServiceProvider::class);

        $this->registerCoreSingleton();
        $this->loadHelpers();

        // $this->registerConfig();
    }

    /**
     * ผูก Core เป็น singleton แหล่งเดียว แล้วให้ helper core() เรียกผ่าน app('core')
     */
    protected function registerCoreSingleton(): void
    {
        // 1) singleton หลัก
        $this->app->singleton('core', function ($app) {
            return $app->make(Core::class);
        });

        // 2) type-hint Core::class → ได้ instance เดียวกันกับ 'core'
        $this->app->alias('core', Core::class);
    }

    /**
     * Register package config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/admin-menu.php',
            'menu.admin'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/acl.php',
            'acl'
        );

        $this->mergeConfigFrom(__DIR__ . '/../config/gametech.php', 'gametech');
    }

    protected function loadHelpers(): void
    {
        $file = __DIR__ . '/../Http/helpers.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
}
