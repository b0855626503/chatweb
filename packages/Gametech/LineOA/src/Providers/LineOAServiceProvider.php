<?php

namespace Gametech\LineOA\Providers;

use Gametech\LineOA\Contracts\LineMemberRegistrar;
use Gametech\LineOA\Services\DefaultLineMemberRegistrar;
use Gametech\LineOA\Services\LineTemplateService;
use Gametech\LineOA\Services\RegisterFlowService;
use Gametech\LineOA\Support\UrlHelper;
use Illuminate\Support\ServiceProvider;

class LineOAServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerConfig();

        $this->app->bind(LineMemberRegistrar::class, DefaultLineMemberRegistrar::class);
        // ถ้าอยากกำหนด cache lifetime ให้ Service

        $this->app->singleton(LineTemplateService::class, function ($app) {
            // cache 60 วินาทีพอ (หรือจะใช้ config ก็ได้)
            return new LineTemplateService(60);
        });

        // RegisterFlowService ใช้ DI ปกติ (ไม่บังคับต้อง singleton แต่จะทำก็ได้)
        $this->app->singleton(RegisterFlowService::class, function ($app) {
            return new RegisterFlowService(
                $app->make(LineTemplateService::class),
                $app->make(LineMemberRegistrar::class)
            );
        });

        $this->app->singleton('lineoa.urlhelper', function () {
            return new UrlHelper();
        });
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

        $this->publishes([
            __DIR__.'/../Database/Seeders/LineTemplateRegisterSeeder.php' =>
                database_path('seeders/LineTemplateRegisterSeeder.php'),
        ], 'line-oa-seeders');

        $this->publishes([
            __DIR__ . '/../Resources/assets/images' => public_path('vendor/line-oa/images'),
        ], 'public');
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
