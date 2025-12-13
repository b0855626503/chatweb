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
    public function boot()
    {
        ConfigProxy::observe(ConfigObserver::class);
    }

    public function register()
    {
        $this->app->bind(ExceptionHandler::class, Handler::class);

        $this->app->register(EventServiceProvider::class);

        $this->registerCoreSingleton();
        $this->loadHelpers();
    }

    /**
     * ผูก Core ให้ไม่วนลูป:
     * - singleton ที่ Core::class
     * - alias 'core' ชี้ไป Core::class
     */
    protected function registerCoreSingleton(): void
    {
        // ถ้า Core constructor autowire ได้ ใช้แบบนี้นิ่งสุด (ไม่ recursion)
        $this->app->singleton(Core::class);

        // ให้เรียก app('core') ได้
        $this->app->alias(Core::class, 'core');

        // หมายเหตุ: อย่าใช้ alias('core', Core::class) + singleton('core')->make(Core::class)
        // เพราะจะวนลูปและกิน memory
    }

    protected function loadHelpers(): void
    {
        $file = __DIR__ . '/../Http/helpers.php';

        if (is_file($file)) {
            require_once $file;
        }
    }
}
