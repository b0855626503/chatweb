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

    /**
     * Bootstrap services.
     */
    public function boot(Router $router)
    {
        AdminProxy::observe(AdminObserver::class);
        RoleProxy::observe(RoleObserver::class);

        /**
         * จุดสำคัญ:
         * - หลีกเลี่ยงการเรียก Core/DB หนัก ๆ ใน CLI / early bootstrap
         * - เมนู/วิวก็ไม่จำเป็นใน console อยู่แล้ว
         */
        if (! $this->app->runningInConsole()) {
            // โหมดจาก Core (มี 1 record)
            // ใช้ app('core') เป็นหลักเพื่อไม่ผูกกับ helper timing
            /** @var Core $core */
            $core = $this->app->bound('core') ? $this->app->make('core') : $this->app->make(Core::class);
            $config = $core->getConfigData();

            if ($config && ($config->seamless ?? null) === 'Y') {
                $this->registerConfigSeamless();
            } else {
                if ($config && ($config->multigame_open ?? null) === 'Y') {
                    $this->registerConfig();
                } else {
                    $this->registerConfigSingle();
                }
            }
        } else {
            // console ไม่ต้องแบก config menu/acl mode
            // ยัง merge config ใน registerConfig* ได้ถ้าคุณต้องการให้ CLI อ่าน config('menu.admin')
            // แต่ส่วนใหญ่ artisan ไม่ต้องใช้เมนู
        }

        // โหลด routes
        $this->loadRoutesFrom(__DIR__ . '/../Http/routes.php');

        // โหลด views
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'admin');

        // ผูก view composers (กัน CLI)
        if (! $this->app->runningInConsole()) {
            $this->composeView();
        }

        // $router->aliasMiddleware('admin', BouncerMiddleware::class);
    }

    /**
     * Register services.
     */
    public function register()
    {
        // ACL + Bouncer
        $this->registerACL();
        $this->registerBouncer();

        // helpers
        $this->loadHelpers();
    }

    /**
     * Register package config (Multi-game).
     */
    protected function registerConfig()
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/Config/admin-menu.php', 'menu.admin');
        $this->mergeConfigFrom(dirname(__DIR__) . '/Config/acl.php', 'acl');
    }

    /**
     * Register package config (Single).
     */
    protected function registerConfigSingle()
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/Config/admin-menu-single.php', 'menu.admin');
        $this->mergeConfigFrom(dirname(__DIR__) . '/Config/acl-single.php', 'acl');
    }

    /**
     * Register package config (Seamless).
     */
    protected function registerConfigSeamless()
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/Config/admin-menu-seamless.php', 'menu.admin');
        $this->mergeConfigFrom(dirname(__DIR__) . '/Config/acl-seamless.php', 'acl');
    }

    /**
     * View composers — คอมโพสเมนูครั้งเดียวบน layout แล้ว share ให้ทุกวิว
     */
    protected function composeView()
    {
        $menuComposer = function ($view) {
            static $menuTree = null; // cache ต่อรีเควสต์

            if ($menuTree) {
                $view->with('menu', $menuTree);
                return;
            }

            $tree = Tree::create();

            // ยังไม่ล็อกอิน → ส่งเมนูว่าง (กัน dereference null)
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

            // เรียงเมนูด้วย core()->sortItems ถ้ามี (ยึดของเดิม)
            $tree->items = core()->sortItems($tree->items);

            // เซ็ต currentRoute ให้ blade เดิมใช้งาน (กันกรณีเดิมพึ่งพา)
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

        // ✅ ACL สำหรับ module views
        view()->composer(['admin::module.*'], function ($view) {
            $view->with('acl', $this->createACL());
        });

        // header: version
        view()->composer(['admin::layouts.*'], function ($view) {
            $this->deleteVersionFile();
            $newpatch = false;
            $current  = config('self-update.version_installed');
            $view->with('version', $current)->with('patch', $newpatch);
        });
    }

    /**
     * Registers acl to entire application
     */
    public function registerACL()
    {
        $this->app->singleton('acl', function () {
            return $this->createACL();
        });
    }

    /**
     * Create acl tree
     */
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

    /**
     * Register Bouncer as a singleton.
     */
    protected function registerBouncer()
    {
        // facade alias (คงไว้เพื่อไม่กระทบของเดิม ถ้ามีที่ไหนใช้ bouncer::...)
        AliasLoader::getInstance()->alias('bouncer', BouncerFacade::class);

        // source of truth: app('bouncer')
        $this->app->singleton('bouncer', function ($app) {
            return $app->make(Bouncer::class);
        });

        // type-hint Bouncer::class → ได้ instance เดียวกับ app('bouncer')
        $this->app->alias('bouncer', Bouncer::class);
    }

    protected function loadHelpers(): void
    {
        $file = __DIR__ . '/../Http/helpers.php';

        if (is_file($file)) {
            require_once $file;
        }
    }
}
