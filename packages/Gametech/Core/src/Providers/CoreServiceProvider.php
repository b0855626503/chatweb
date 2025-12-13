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
        $this->app->bind(ExceptionHandler::class, Handler::class);

        // ถ้ามี event provider ของ core เอง
        $this->app->register(EventServiceProvider::class);

        // source of truth ของ "core" ให้นิ่งเป็น singleton เดียว
        $this->registerCoreSingleton();

        // โหลด helpers (ยังคงไว้ได้ แม้คุณจะ autoload ผ่าน composer แล้วก็ตาม)
        $this->loadHelpers();

        // $this->registerConfig();
    }

    /**
     * ผูก Core เป็น singleton แหล่งเดียว และทำ alias ให้ type-hint ได้ instance เดียวกัน
     */
    protected function registerCoreSingleton(): void
    {
        // 1) singleton หลัก: app('core')
        $this->app->singleton('core', function ($app) {
            return $app->make(Core::class);
        });

        // 2) ให้ app(Core::class) ได้ instance เดียวกับ app('core')
        //    หมายเหตุ: alias signature คือ alias($abstract, $alias)
        //    ดังนั้นให้ 'core' เป็น abstract แล้ว alias เป็น Core::class
        $this->app->alias('core', Core::class);
    }

    /**
     * Register package config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/Config/admin-menu.php', 'menu.admin');
        $this->mergeConfigFrom(dirname(__DIR__) . '/Config/acl.php', 'acl');
        $this->mergeConfigFrom(__DIR__ . '/../config/gametech.php', 'gametech');
    }

    /**
     * โหลด helper functions ของ Core
     *
     * หมายเหตุ:
     * - ถ้าคุณใส่ helpers ไว้ใน composer.json autoload.files แล้ว
     *   เมธอดนี้จะกลายเป็น redundant แต่ไม่พัง เพราะ require_once กันซ้ำ
     */
    protected function loadHelpers(): void
    {
        $file = __DIR__ . '/../Http/helpers.php';

        if (is_file($file)) {
            require_once $file;
        }
    }
}
