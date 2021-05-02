<?php

namespace Gametech\Admin\Providers;


use Codedge\Updater\UpdaterFacade;
use Gametech\Admin\Bouncer;
use Gametech\Admin\Facades\Bouncer as BouncerFacade;

use Gametech\Core\Tree;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\ServiceProvider;


class AdminServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @param Router $router
     * @return void
     */
    public function boot(Router $router)
    {

        include __DIR__ . '/../Http/helpers.php';

//        $router->aliasMiddleware('admin', BouncerMiddleware::class);

    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->loadRoutesFrom(__DIR__ . '/../Http/routes.php');

        $this->publishes([
            __DIR__ . '/../../publishable/assets' => public_path('assets/admin'),
        ], 'public');

        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'admin');

        $this->composeView();

        $this->registerACL();

        $this->registerConfig();

        $this->registerBouncer();


//        $this->loadViewsFrom(__DIR__.'/resources/views/', 'LaravelLogger');
//        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

//        $this->registerEventListeners();
//        $this->publishFiles();
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


    /**
     * Bind the the data to the views
     *
     * @return void
     */
    protected function composeView()
    {
        view()->composer(['admin::layouts.master','admin::module.*'], function ($view) {
            $tree = Tree::create();

            $permissionType = Auth::guard('admin')->user()->role->permission_type;
            $allowedPermissions = Auth::guard('admin')->user()->role->permissions;

            foreach (config('menu.admin') as $index => $item) {
                if (! bouncer()->hasPermission($item['key'])) {
                    continue;
                }

                if ($index + 1 < count(config('menu.admin')) && $permissionType != 'all') {
                    $permission = config('menu.admin')[$index + 1];

                    if (substr_count($permission['key'], '.') == 2 && substr_count($item['key'], '.') == 1) {
                        foreach ($allowedPermissions as $key => $value) {
                            if ($item['key'] == $value) {
                                $neededItem = $allowedPermissions[$key + 1];

                                foreach (config('menu.admin') as $key1 => $findMatced) {
                                    if ($findMatced['key'] == $neededItem) {
                                        $item['route'] = $findMatced['route'];
                                    }
                                }
                            }
                        }
                    }
                }

                $tree->add($item, 'menu');
            }

            $tree->items = core()->sortItems($tree->items);

            $view->with('menu', $tree);

        });

        view()->composer(['admin::module.*'], function ($view) {
            $view->with('acl', $this->createACL());
        });

        view()->composer(['admin::layouts.header'], function ($view) {

            $current = UpdaterFacade::source()->getVersionInstalled();
            if(UpdaterFacade::source()->isNewVersionAvailable($current)){
                $versionAvailable =  UpdaterFacade::source()->getVersionAvailable();
                $current = '<a href="'.route('admin.update.index').'" style="font-size: 1.0rem;margin: 0 auto;font-weight:700;color:red"> >> มีอัพเดทเวอชั่นใหม่ '.$versionAvailable.' กดตรงนี้เพื่ออัพเดท<< </a>';
            }else{
                $current = '';
            }

            $view->with('version', $current);
        });

    }

    /**
     * Registers acl to entire application
     *
     * @return void
     */
    public function registerACL()
    {
        $this->app->singleton('acl', function () {
            return $this->createACL();
        });
    }

    /**
     * Create acl tree
     *
     * @return mixed
     */
    public function createACL()
    {
        static $tree;

        if ($tree) {
            return $tree;
        }

        $tree = Tree::create();

        foreach (config('acl') as $item) {
            $tree->add($item, 'acl');
        }

        $tree->items = core()->sortItems($tree->items);

        return $tree;
    }

    /**
     * Register Bouncer as a singleton.
     *
     * @return void
     */
    protected function registerBouncer()
    {
        $loader = AliasLoader::getInstance();
        $loader->alias('bouncer', BouncerFacade::class);

        $this->app->singleton('bouncer', function () {
            return app()->make(Bouncer::class);
        });
    }

}
