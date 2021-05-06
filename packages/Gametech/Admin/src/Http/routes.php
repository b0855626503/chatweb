<?php
Route::domain(config('app.admin_url') . '.' . (is_null(config('app.admin_domain_url')) ? config('app.domain_url') : config('app.admin_domain_url')))->group(function () {


//Route::prefix('admin')->group(function () {


    Route::group(['middleware' => ['web', 'logadmin'], 'namespace' => 'Gametech\Admin\Http\Controllers'], function () {


        Route::get('/', 'Controller@redirectToLogin')->name('admin');



        // Login Routes
        Route::get('/login', 'LoginController@show')->defaults('_config', [
            'view' => 'admin::auth.login',
        ])->name('admin.session.index');

        //login post route to admin auth controller
        Route::post('login', 'LoginController@login')->defaults('_config', [
            'redirect' => 'admin.2fa.validate',
        ])->name('admin.session.create');

        Route::prefix('auth')->group(function () {
            Route::get('/', 'Google2FAController@show2faForm')->defaults('_config', [
                'view' => 'admin::2fa.setting',
            ])->name('admin.2fa.setting')->middleware('auth');

            Route::post('/enable', 'Google2FAController@enable2fa')->name('admin.2fa.enable')->middleware('auth');

            Route::get('/activate', 'Google2FAController@reActivate')->name('admin.2fa.activate')->middleware('auth');

            Route::post('/', 'Google2FAController@index')->name('2fa')->middleware(['auth', '2fa']);

//            Route::post('/', function (Request $request) {
//                return redirect(URL()->previous());
//            })->name('2fa')->middleware(['auth', '2fa']);


        });


        Route::group(['middleware' => ['admin', 'auth', '2fa']], function () {

            Route::get('/update', 'CmdController@updatePatch')->name('admin.update.index');

            Route::get('/link', 'CmdController@storeLink');

            Route::get('/webservice/start', 'CmdController@webServiceStart');

            Route::get('/webservice/stop', 'CmdController@webServiceStop');

            Route::get('/optimize/clear', 'CmdController@optimizeClear');

            Route::get('/optimize', 'CmdController@optimize');

            Route::get('/optimize/view', 'CmdController@viewCmd');

            Route::get('/cache/clear', 'CmdController@cacheCmd');


            Route::post('broadcasting/auth', 'BroadcastController@authenticate');


            Route::get('/logout', 'LoginController@logout')->defaults('_config', [
                'redirect' => 'admin.session.index',
            ])->name('admin.session.destroy')->withoutMiddleware(['2fa']);

            // Dashboard Route
            Route::get('dashboard', 'DashboardController@index')->defaults('_config', [
                'view' => 'admin::module.dashboard.index',
            ])->name('admin.home.index');

            Route::get('loadcnt', 'DashboardController@loadCnt')->name('admin.home.loadcnt')->withoutMiddleware(['logadmin']);
            Route::post('dashboard/loadsum', 'DashboardController@loadSum')->name('admin.dashboard.loadsum')->withoutMiddleware(['logadmin']);
            Route::post('dashboard/loadsumall', 'DashboardController@loadSumAll')->name('admin.dashboard.loadsumall')->withoutMiddleware(['logadmin']);
            Route::post('dashboard/loadbank', 'DashboardController@loadBank')->name('admin.dashboard.loadbank')->withoutMiddleware(['logadmin']);


            Route::get('rp_log_cashback', 'ReportController@rp_log_cashback')->defaults('_config', [
                'view' => 'admin::module.rp_log_cashback.index',
            ])->name('admin.rp_log_cashback.index');

            Route::get('rp_log_ic', 'ReportController@rp_log_ic')->defaults('_config', [
                'view' => 'admin::module.rp_log_ic.index',
            ])->name('admin.rp_log_ic.index');

            Route::get('rp_wallet', 'ReportController@rp_wallet')->defaults('_config', [
                'view' => 'admin::module.rp_wallet.index',
            ])->name('admin.rp_wallet.index');

            Route::get('rp_credit', 'ReportController@rp_credit')->defaults('_config', [
                'view' => 'admin::module.rp_credit.index',
            ])->name('admin.rp_credit.index');

            Route::get('rp_bill', 'ReportController@rp_bill')->defaults('_config', [
                'view' => 'admin::module.rp_bill.index',
            ])->name('admin.rp_bill.index');

            Route::get('rp_top_promotion', 'ReportController@rp_top_promotion')->defaults('_config', [
                'view' => 'admin::module.rp_top_promotion.index',
            ])->name('admin.rp_top_promotion.index');

            Route::get('rp_online_behavior', 'ReportController@rp_online_behavior')->defaults('_config', [
                'view' => 'admin::module.rp_online_behavior.index',
            ])->name('admin.rp_online_behavior.index');

            Route::get('rp_bill_free', 'ReportController@rp_bill_free')->defaults('_config', [
                'view' => 'admin::module.rp_bill_free.index',
            ])->name('admin.rp_bill_free.index');

            Route::get('rp_user_log', 'ReportController@rp_user_log')->defaults('_config', [
                'view' => 'admin::module.rp_user_log.index',
            ])->name('admin.rp_user_log.index')->withoutMiddleware(['logadmin']);

            Route::get('rp_staff_log', 'ReportController@rp_staff_log')->defaults('_config', [
                'view' => 'admin::module.rp_staff_log.index',
            ])->name('admin.rp_staff_log.index')->withoutMiddleware(['logadmin']);


            Route::get('rp_deposit', 'ReportController@rp_deposit')->defaults('_config', [
                'view' => 'admin::module.rp_deposit.index',
            ])->name('admin.rp_deposit.index');

            Route::get('rp_withdraw', 'ReportController@rp_withdraw')->defaults('_config', [
                'view' => 'admin::module.rp_withdraw.index',
            ])->name('admin.rp_withdraw.index');

            Route::get('rp_withdraw_free', 'ReportController@rp_withdraw_free')->defaults('_config', [
                'view' => 'admin::module.rp_withdraw_free.index',
            ])->name('admin.rp_withdraw_free.index');

            Route::get('rp_spin', 'ReportController@rp_spin')->defaults('_config', [
                'view' => 'admin::module.rp_spin.index',
            ])->name('admin.rp_spin.index');

            Route::get('rp_billturn', 'ReportController@rp_billturn')->defaults('_config', [
                'view' => 'admin::module.rp_billturn.index',
            ])->name('admin.rp_billturn.index');

            Route::get('rp_cashback', 'ReportController@rp_cashback')->defaults('_config', [
                'view' => 'admin::module.rp_cashback.index',
            ])->name('admin.rp_cashback.index');

            Route::post('rp_cashback', 'CashbackICController@Cashback')->defaults('_config', [
                'view' => 'admin::module.rp_cashback.index',
            ])->name('admin.rp_cashback.store');

            Route::get('rp_member_ic', 'ReportController@rp_member_ic')->defaults('_config', [
                'view' => 'admin::module.rp_member_ic.index',
            ])->name('admin.rp_member_ic.index');

            Route::post('rp_member_ic', 'CashbackICController@MemberIC')->defaults('_config', [
                'view' => 'admin::module.rp_member_ic.index',
            ])->name('admin.rp_member_ic.store');

            Route::get('rp_sponsor', 'ReportController@rp_sponsor')->defaults('_config', [
                'view' => 'admin::module.rp_sponsor.index',
            ])->name('admin.rp_sponsor.index');

            Route::get('rp_reward_point', 'ReportController@rp_sponsor')->defaults('_config', [
                'view' => 'admin::module.rp_sponsor.index',
            ])->name('admin.rp_sponsor.index');

            Route::get('rp_alllog', 'ReportController@rp_alllog')->defaults('_config', [
                'view' => 'admin::module.rp_alllog.index',
            ])->name('admin.rp_alllog.index');

            Route::get('rp_sum_game', 'ReportController@rp_sum_game')->defaults('_config', [
                'view' => 'admin::module.rp_sum_game.index',
            ])->name('admin.rp_sum_game.index');

            Route::get('rp_sum_stat', 'ReportController@rp_sum_stat')->defaults('_config', [
                'view' => 'admin::module.rp_sum_stat.index',
            ])->name('admin.rp_sum_stat.index');

            Route::post('rp_sum_stat/loaddata', 'ReportController@loadData')->name('admin.rp_sum_stat.loaddata');


            Route::get('rp_sum_payment', 'ReportController@rp_sum_payment')->defaults('_config', [
                'view' => 'admin::module.rp_sum_payment.index',
            ])->name('admin.rp_sum_payment.index');

            Route::get('rp_setpoint', 'ReportController@rp_setpoint')->defaults('_config', [
                'view' => 'admin::module.rp_setpoint.index',
            ])->name('admin.rp_setpoint.index');

            Route::get('rp_setdiamond', 'ReportController@rp_setdiamond')->defaults('_config', [
                'view' => 'admin::module.rp_setdiamond.index',
            ])->name('admin.rp_setdiamond.index');


            $route = ['name' => 'bank', 'controller' => 'BankController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('loadgame', $route['controller'] . '@loadGame')->name('admin.' . $route['name'] . '.loadgame');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });

            $route = ['name' => 'bank_rule', 'controller' => 'BankRuleController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loadbank', $route['controller'] . '@loadBank')->name('admin.' . $route['name'] . '.loadbank');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('loadgame', $route['controller'] . '@loadGame')->name('admin.' . $route['name'] . '.loadgame');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });


            $route = ['name' => 'refer', 'controller' => 'ReferController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('loadgame', $route['controller'] . '@loadGame')->name('admin.' . $route['name'] . '.loadgame');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });

            $route = ['name' => 'spin', 'controller' => 'SpinController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('loadgame', $route['controller'] . '@loadGame')->name('admin.' . $route['name'] . '.loadgame');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });


            $route = ['name' => 'confirm_wallet', 'controller' => 'ConfirmwalletController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('loadgame', $route['controller'] . '@loadGame')->name('admin.' . $route['name'] . '.loadgame');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });


            $route = ['name' => 'employees', 'controller' => 'AdminController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('loadrole', $route['controller'] . '@loadRole')->name('admin.' . $route['name'] . '.loadrole');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });


            $route = ['name' => 'roles', 'controller' => 'RoleController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('loadgame', $route['controller'] . '@loadGame')->name('admin.' . $route['name'] . '.loadgame');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });

            $route = ['name' => 'announces', 'controller' => 'AnnounceController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('loadgame', $route['controller'] . '@loadGame')->name('admin.' . $route['name'] . '.loadgame');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->defaults('_config', [
                    'redirect' => 'admin.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });


            $route = ['name' => 'batch_user', 'controller' => 'BatchUserController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('loadgame', $route['controller'] . '@loadGame')->name('admin.' . $route['name'] . '.loadgame');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });

            $route = ['name' => 'setting', 'controller' => 'ConfigController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::get('getrule', $route['controller'] . '@getrule')->name('admin.' . $route['name'] . '.getrule');

                Route::post('loadbank', $route['controller'] . '@loadBank')->name('admin.' . $route['name'] . '.loadbank');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });


            $route = ['name' => 'withdraw', 'controller' => 'WithdrawController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('loadbank', $route['controller'] . '@loadBank')->name('admin.' . $route['name'] . '.loadbank');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });


            $route = ['name' => 'withdraw_free', 'controller' => 'WithdrawfreeController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('loadbank', $route['controller'] . '@loadBank')->name('admin.' . $route['name'] . '.loadbank');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });

            $route = ['name' => 'member', 'controller' => 'MemberController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');


                Route::post('export', $route['controller'] . '@export')->name('admin.' . $route['name'] . '.export');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('createsub', $route['controller'] . '@createsub')->name('admin.' . $route['name'] . '.createsub');

                Route::get('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::get('loadbank', $route['controller'] . '@loadBank')->name('admin.' . $route['name'] . '.loadbank');

                Route::get('loadbankaccount', $route['controller'] . '@loadBankAccount')->name('admin.' . $route['name'] . '.loadbankaccount');

                Route::get('gamelog', $route['controller'] . '@gameLog')->name('admin.' . $route['name'] . '.gamelog');

                Route::get('remark', $route['controller'] . '@remark')->name('admin.' . $route['name'] . '.remark');

                Route::post('setwallet', $route['controller'] . '@setWallet')->name('admin.' . $route['name'] . '.setwallet');

                Route::post('setpoint', $route['controller'] . '@setPoint')->name('admin.' . $route['name'] . '.setpoint');

                Route::post('setdiamond', $route['controller'] . '@setDiamond')->name('admin.' . $route['name'] . '.setdiamond');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('editsub', $route['controller'] . '@editsub')->name('admin.' . $route['name'] . '.editsub');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

                Route::post('deletesub', $route['controller'] . '@destroysub')->name('admin.' . $route['name'] . '.deletesub');


                Route::post('refill', $route['controller'] . '@refill')->name('admin.' . $route['name'] . '.refill');

            });

            $route = ['name' => 'member_free', 'controller' => 'MemberfreeController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('loadbank', $route['controller'] . '@loadBank')->name('admin.' . $route['name'] . '.loadbank');

                Route::post('loadbankaccount', $route['controller'] . '@loadBankAccount')->name('admin.' . $route['name'] . '.loadbankaccount');

                Route::post('gamelog', $route['controller'] . '@gameLog')->name('admin.' . $route['name'] . '.gamelog');

                Route::post('setwallet', $route['controller'] . '@setWallet')->name('admin.' . $route['name'] . '.setwallet');

                Route::post('setpoint', $route['controller'] . '@setPoint')->name('admin.' . $route['name'] . '.setpoint');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });

            $route = ['name' => 'member_confirm', 'controller' => 'MemberConfirmController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });

            $route = ['name' => 'bank_in', 'controller' => 'BankinController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });

            $route = ['name' => 'bank_out', 'controller' => 'BankoutController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });

            $route = ['name' => 'bank_account_in', 'controller' => 'BankAccountInController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('loadbank', $route['controller'] . '@loadBank')->name('admin.' . $route['name'] . '.loadbank');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });

            $route = ['name' => 'bank_account_out', 'controller' => 'BankAccountOutController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('loadbank', $route['controller'] . '@loadBank')->name('admin.' . $route['name'] . '.loadbank');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });


            $route = ['name' => 'faq', 'controller' => 'FaqController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });

            $route = ['name' => 'game', 'controller' => 'GameController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('loaddebug', $route['controller'] . '@loadDebug')->name('admin.' . $route['name'] . '.loaddebug');

                Route::post('loaddebugfree', $route['controller'] . '@loadDebugFree')->name('admin.' . $route['name'] . '.loaddebugfree');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });

            $route = ['name' => 'promotion', 'controller' => 'PromotionController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('createsub', $route['controller'] . '@createsub')->name('admin.' . $route['name'] . '.createsub');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('loadpro', $route['controller'] . '@loadPro')->name('admin.' . $route['name'] . '.loadpro');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

                Route::post('deletesub', $route['controller'] . '@destroysub')->name('admin.' . $route['name'] . '.deletesub');

            });

            $route = ['name' => 'pro_content', 'controller' => 'PromotionContentController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('loadpro', $route['controller'] . '@loadPro')->name('admin.' . $route['name'] . '.loadpro');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });

            $route = ['name' => 'reward', 'controller' => 'RewardController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('loadgame', $route['controller'] . '@loadGame')->name('admin.' . $route['name'] . '.loadgame');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });

            $route = ['name' => 'rp_reward_point', 'controller' => 'RewardPointController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });


            $route = ['name' => 'payment', 'controller' => 'PaymentController'];
            Route::group(['prefix' => $route['name']], function () use ($route) {
                Route::get('/', $route['controller'] . '@index')->defaults('_config', [
                    'view' => 'admin::module.' . $route['name'] . '.index',
                ])->name('admin.' . $route['name'] . '.index');

                Route::post('create', $route['controller'] . '@create')->name('admin.' . $route['name'] . '.create');

                Route::post('loaddata', $route['controller'] . '@loadData')->name('admin.' . $route['name'] . '.loaddata');

                Route::post('edit', $route['controller'] . '@edit')->name('admin.' . $route['name'] . '.edit');

                Route::post('clear', $route['controller'] . '@clear')->name('admin.' . $route['name'] . '.clear');

                Route::post('update/{id?}', $route['controller'] . '@update')->name('admin.' . $route['name'] . '.update');

                Route::post('delete', $route['controller'] . '@destroy')->name('admin.' . $route['name'] . '.delete');

            });


        });


    });

//    });

});
