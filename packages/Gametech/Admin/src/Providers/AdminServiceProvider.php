<?php

namespace Gametech\Admin\Providers;

use Codedge\Updater\Traits\UseVersionFile;
use Gametech\Admin\Bouncer;
use Gametech\Admin\Facades\Bouncer as BouncerFacade;
use Gametech\Admin\Models\AdminProxy;
use Gametech\Admin\Models\RoleProxy;
use Gametech\Admin\Observers\AdminObserver;
use Gametech\Admin\Observers\RoleObserver;
use Gametech\Core\Core;
use Gametech\Core\Tree;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AdminServiceProvider extends ServiceProvider
{
    use UseVersionFile;

    public function boot(Router $router)
    {
        AdminProxy::observe(AdminObserver::class);
        RoleProxy::observe(RoleObserver::class);

        // ลดงานหนักใน CLI
        if (! $this->app->runningInConsole()) {
            // ใช้ core() ได้ (หลังแก้ CoreServiceProvider แล้วจะไม่วนลูป)
            $config = core()->getConfigData();

            if (($config->seamless ?? null) === 'Y') {
                $this->registerConfigSeamless();
            } else {
                if (($config->multigame_open ?? null) === 'Y') {
                    $this->registerConfig();
                } else {
                    $this->registerConfigSingle();
                }
            }
        }

        $this->loadRoutesFrom(__DIR__ . '/../Http/routes.php');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'admin');

        if (! $this->app->runningInConsole()) {
            $this->composeView();
        }
    }

    public function register()
    {
        $this->registerACL();
        $this->registerBouncer();
        $this->loadHelpers();
    }

    protected function registerConfig()
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/Config/admin-menu.php', 'menu.admin');
        $this->mergeConfigFrom(dirname(__DIR__) . '/Config/acl.php', 'acl');
    }

    protected function registerConfigSingle()
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/Config/admin-menu-single.php', 'menu.admin');
        $this->mergeConfigFrom(dirname(__DIR__) . '/Config/acl-single.php', 'acl');
    }

    protected function registerConfigSeamless()
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/Config/admin-menu-seamless.php', 'menu.admin');
        $this->mergeConfigFrom(dirname(__DIR__) . '/Config/acl-seamless.php', 'acl');
    }

    protected function composeView()
    {
        $menuComposer = function ($view) {
            static $menuTree = null;

            if ($menuTree) {
                $view->with('menu', $menuTree);
                return;
            }

            $tree = Tree::create();

            $user = Auth::guard('admin')->user();
            if (! $user) {
                $menuTree = $tree;
                $view->with('menu', $menuTree);
                return;
            }

            $permissionType     = $user->role->permission_type ?? 'all';
            $allowedPermissions = (array) ($user->role->permissions ?? []);

            $menu = (array) config('menu.admin');

            foreach ($menu as $index => $item) {
                if (! bouncer()->hasPermission($item['key'])) {
                    continue;
                }

                if ($index + 1 < count($menu) && $permissionType !== 'all') {
                    $permission = $menu[$index + 1] ?? null;

                    if ($permission
                        && substr_count(($permission['key'] ?? ''), '.') == 2
                        && substr_count(($item['key'] ?? ''), '.') == 1) {

                        foreach ($allowedPermissions as $k => $v) {
                            if (($item['key'] ?? null) === $v) {
                                $neededItem = $allowedPermissions[$k + 1] ?? null;

                                if ($neededItem) {
                                    foreach ($menu as $candidate) {
                                        if (($candidate['key'] ?? null) === $neededItem) {
                                            $item['route'] = $candidate['route'] ?? ($item['route'] ?? null);
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                $tree->add($item, 'menu');
            }

            $tree->items = core()->sortItems($tree->items);

            $routeName = Route::currentRouteName() ?? '';
            $group     = Str::before(Str::after($routeName, 'admin.'), '.');
            if ($group) {
                $tree->currentRoute = $group;
            }

            $menuTree = $tree;
            $view->with('menu', $menuTree);
        };

        view()->composer('admin::layouts.*', $menuComposer);
        view()->composer('admin::module.*', $menuComposer);

        view()->composer(['admin::module.*'], function ($view) {
            $view->with('acl', $this->createACL());
        });

        view()->composer(['admin::layouts.*'], function ($view) {
            $this->deleteVersionFile();
            $newpatch = false;
            $current  = config('self-update.version_installed');
            $view->with('version', $current)->with('patch', $newpatch);
        });
    }

    public function registerACL()
    {
        $this->app->singleton('acl', function () {
            return $this->createACL();
        });
    }

    public function createACL()
    {
        static $tree;

        if ($tree) {
            return $tree;
        }

        $tree = Tree::create();

        foreach ((array) config('acl') as $item) {
            $tree->add($item, 'acl');
        }

        $tree->items = core()->sortItems($tree->items);

        return $tree;
    }

    protected function registerBouncer()
    {
        AliasLoader::getInstance()->alias('bouncer', BouncerFacade::class);

        // source of truth = Bouncer::class
        $this->app->singleton(Bouncer::class);

        // เรียกได้ทั้ง app('bouncer') และ type-hint Bouncer::class
        $this->app->alias(Bouncer::class, 'bouncer');
    }

    protected function loadHelpers(): void
    {
        $file = __DIR__ . '/../Http/helpers.php';

        if (is_file($file)) {
            require_once $file;
        }
    }
}
