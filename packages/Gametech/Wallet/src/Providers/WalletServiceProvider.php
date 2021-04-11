<?php

namespace Gametech\Wallet\Providers;

use Gametech\Wallet\Http\Middleware\RedirectIfNotCustomer;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;


class WalletServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @param Router $router
     * @return void
     */
    public function boot(Router $router)
    {
//        $router->aliasMiddleware('customer', RedirectIfNotCustomer::class);


    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
//        $this->registerConfig();
        $this->loadRoutesFrom(__DIR__ . '/../Http/routes.php');

        $this->publishes([
            __DIR__ . '/../../publishable/assets' => public_path('assets/main'),
        ], 'public');

        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'wallet');
    }

    /**
     * Register package config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/admin-menu.php', 'menu.admin'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/acl.php', 'acl'
        );
    }
}
