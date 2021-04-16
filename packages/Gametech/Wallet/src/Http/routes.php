<?php

use Illuminate\Support\Facades\Route;

Route::domain(config('app.user_url') . '.' . config('app.user_domain_url'))->group(function () {

    Route::group(['middleware' => ['web', 'loguser']], function () {

        Route::get('/', 'Gametech\Wallet\Http\Controllers\Controller@redirectToLogin');


        Route::get('/login', 'Gametech\Wallet\Http\Controllers\LoginController@show')->defaults('_config', [
            'view' => 'wallet::customer.sessions.create'
        ])->name('customer.session.index');

        Route::post('login', 'Gametech\Wallet\Http\Controllers\LoginController@login')->defaults('_config', [
            'redirect' => 'customer.home.index'
        ])->name('customer.session.create');

        Route::get('register/{id?}', 'Gametech\Wallet\Http\Controllers\LoginController@store')->defaults('_config', [
            'view' => 'wallet::customer.sessions.store'
        ])->name('customer.session.store');

        Route::post('checkacc', 'Gametech\Wallet\Http\Controllers\LoginController@checkAcc')->defaults('_config', [
            'redirect' => 'customer.home.index'
        ])->name('customer.checkacc.index')->withoutMiddleware(['loguser']);

        Route::post('register', 'Gametech\Wallet\Http\Controllers\LoginController@register')->defaults('_config', [
            'redirect' => 'customer.session.index'
        ])->name('customer.session.register');

        Route::get('test', 'Gametech\Wallet\Http\Controllers\TestController@index')->defaults('_config', [
            'view' => 'wallet::customer.test.index'
        ])->name('customer.test.index');

        Route::get('download', 'Gametech\Wallet\Http\Controllers\LoginController@download')->defaults('_config', [
            'view' => 'wallet::customer.download.home',
        ])->name('customer.home.download');


        Route::prefix('member')->group(function () {

            Route::group(['middleware' => ['customer']], function () {

                Route::get('logout', 'Gametech\Wallet\Http\Controllers\LoginController@logout')->defaults('_config', [
                    'redirect' => 'customer.session.index'
                ])->name('customer.session.destroy');

                Route::get('/', 'Gametech\Wallet\Http\Controllers\HomeController@index')->defaults('_config', [
                    'view' => 'wallet::customer.home.index',
                ])->name('customer.home.index');

                Route::get('/loadprofile', 'Gametech\Wallet\Http\Controllers\HomeController@loadProfile')->name('customer.home.profile')->withoutMiddleware(['loguser']);

                Route::get('/loadgame/{id}', 'Gametech\Wallet\Http\Controllers\HomeController@loadGameID')->name('customer.home.loadgameid')->withoutMiddleware(['loguser']);

                Route::get('/loadgamefree/{id}', 'Gametech\Wallet\Http\Controllers\HomeController@loadGameFreeID')->name('customer.home.loadgamefreeid')->withoutMiddleware(['loguser']);

                Route::post('create', 'Gametech\Wallet\Http\Controllers\HomeController@create')->name('customer.home.create');

                Route::post('createfree', 'Gametech\Wallet\Http\Controllers\HomeController@createfree')->name('customer.home.createfree');


                Route::get('topup', 'Gametech\Wallet\Http\Controllers\TopupController@index')->defaults('_config', [
                    'view' => 'wallet::customer.topup.index',
                ])->name('customer.topup.index');

                Route::get('topuptest', 'Gametech\Wallet\Http\Controllers\TopupController@indextest')->defaults('_config', [
                    'view' => 'wallet::customer.topup.indextest',
                ])->name('customer.topup.indextest');

                Route::get('history', 'Gametech\Wallet\Http\Controllers\HistoryController@index')->defaults('_config', [
                    'view' => 'wallet::customer.history.index',
                ])->name('customer.history.index');

                Route::post('history', 'Gametech\Wallet\Http\Controllers\HistoryController@store')->name('customer.history.store');

                Route::get('profile', 'Gametech\Wallet\Http\Controllers\ProfileController@index')->defaults('_config', [
                    'view' => 'wallet::customer.profile.index',
                ])->name('customer.profile.index');

                Route::post('profile/view', 'Gametech\Wallet\Http\Controllers\ProfileController@view')->name('customer.profile.view');

                Route::post('profile/viewfree', 'Gametech\Wallet\Http\Controllers\ProfileController@viewfree')->name('customer.profile.viewfree');


                Route::post('profile/changepass', 'Gametech\Wallet\Http\Controllers\ProfileController@changepass')->name('customer.profile.changepass');

                Route::post('profile/resetgamepass', 'Gametech\Wallet\Http\Controllers\ProfileController@resetgamepass')->name('customer.profile.resetgamepass');

                Route::post('profile/resetgamefreepass', 'Gametech\Wallet\Http\Controllers\ProfileController@resetgamefreepass')->name('customer.profile.resetgamefreepass');


                Route::get('point', 'Gametech\Wallet\Http\Controllers\PointController@index')->defaults('_config', [
                    'view' => 'wallet::customer.reward.index',
                ])->name('customer.reward.index');

                Route::post('point', 'Gametech\Wallet\Http\Controllers\PointController@store')->defaults('_config', [

                ])->name('customer.reward.store');

                Route::get('point/history', 'Gametech\Wallet\Http\Controllers\PointController@history')->defaults('_config', [
                    'view' => 'wallet::customer.reward_history.index',
                ])->name('customer.reward_history.index');


                Route::get('reward', 'Gametech\Wallet\Http\Controllers\SpinController@index')->defaults('_config', [
                    'view' => 'wallet::customer.spin.index',
                ])->name('customer.spin.index');

                Route::post('reward', 'Gametech\Wallet\Http\Controllers\SpinController@store')->defaults('_config', [

                ])->name('customer.spin.store');

                Route::get('reward/history', 'Gametech\Wallet\Http\Controllers\SpinController@history')->defaults('_config', [
                    'view' => 'wallet::customer.spin_history.index',
                ])->name('customer.spin_history.index');

                Route::get('manual', 'Gametech\Wallet\Http\Controllers\ManualController@index')->defaults('_config', [
                    'view' => 'wallet::customer.manual.index',
                ])->name('customer.manual.index');


                Route::get('download', 'Gametech\Wallet\Http\Controllers\DownloadController@index')->defaults('_config', [
                    'view' => 'wallet::customer.download.index',
                ])->name('customer.download.index');

                Route::get('promotion', 'Gametech\Wallet\Http\Controllers\PromotionController@index')->defaults('_config', [
                    'view' => 'wallet::customer.promotion.index',
                ])->name('customer.promotion.index');

                Route::get('contributor', 'Gametech\Wallet\Http\Controllers\ContributorController@index')->defaults('_config', [
                    'view' => 'wallet::customer.contributor.index',
                ])->name('customer.contributor.index');

                Route::get('contributortest', 'Gametech\Wallet\Http\Controllers\ContributorController@indextest')->defaults('_config', [
                    'view' => 'wallet::customer.contributor.indextest',
                ])->name('customer.contributor.indextest');

                Route::post('contributor', 'Gametech\Wallet\Http\Controllers\ContributorController@store')->name('customer.contributor.store');


                Route::get('withdraw', 'Gametech\Wallet\Http\Controllers\WithdrawController@index')->defaults('_config', [
                    'view' => 'wallet::customer.withdraw.index',
                ])->name('customer.withdraw.index');

                Route::post('withdraw/request', 'Gametech\Wallet\Http\Controllers\WithdrawController@store')->defaults('_config', [
                    'redirect' => 'customer.withdraw.index'
                ])->name('customer.withdraw.store')->block($lockSeconds = 30, $waitSeconds = 30);


                Route::get('credit', 'Gametech\Wallet\Http\Controllers\CreditController@index')->defaults('_config', [
                    'view' => 'wallet::customer.credit.index',
                ])->name('customer.credit.index');


                Route::get('credit/history', 'Gametech\Wallet\Http\Controllers\CreditHistoryController@index')->defaults('_config', [
                    'view' => 'wallet::customer.credit.history.index',
                ])->name('customer.credit.history.index');

                Route::post('credit/history', 'Gametech\Wallet\Http\Controllers\CreditHistoryController@store')->name('customer.credit.history.store');

                Route::get('credit/withdraw', 'Gametech\Wallet\Http\Controllers\CreditWithdrawController@index')->defaults('_config', [
                    'view' => 'wallet::customer.credit.withdraw.index',
                ])->name('customer.credit.withdraw.index');

                Route::post('credit/withdraw/request', 'Gametech\Wallet\Http\Controllers\CreditWithdrawController@store')->defaults('_config', [
                    'redirect' => 'customer.credit.withdraw.index'
                ])->name('customer.credit.withdraw.store')->block($lockSeconds = 30, $waitSeconds = 30);

                Route::get('credit/transfer/game', 'Gametech\Wallet\Http\Controllers\CreditTransferGameController@index')->defaults('_config', [
                    'view' => 'wallet::customer.credit.transfer.game.index',
                ])->name('customer.credit.transfer.game.index');

                Route::post('credit/transfer/game/check', 'Gametech\Wallet\Http\Controllers\CreditTransferGameController@check')->defaults('_config', [
                    'redirect' => 'customer.credit.transfer.game.index',
                    'view' => 'wallet::customer.credit.transfer.game.check',
                ])->name('customer.credit.transfer.game.check')->block($lockSeconds = 30, $waitSeconds = 30);

                Route::post('credit/transfer/game/confirm', 'Gametech\Wallet\Http\Controllers\CreditTransferGameController@confirm')->defaults('_config', [

                ])->name('customer.credit.transfer.game.confirm')->block($lockSeconds = 30, $waitSeconds = 30);

                Route::get('credit/transfer/game/complete', 'Gametech\Wallet\Http\Controllers\CreditTransferGameController@complete')->defaults('_config', [
                    'view' => 'wallet::customer.credit.transfer.game.complete',
                ])->name('customer.credit.transfer.game.complete')->block($lockSeconds = 30, $waitSeconds = 30);

                Route::get('credit/transfer/wallet', 'Gametech\Wallet\Http\Controllers\CreditTransferWalletController@index')->defaults('_config', [
                    'view' => 'wallet::customer.credit.transfer.wallet.index',
                ])->name('customer.credit.transfer.wallet.index');

                Route::post('credit/transfer/wallet/check', 'Gametech\Wallet\Http\Controllers\CreditTransferWalletController@check')->defaults('_config', [
                    'redirect' => 'customer.credit.transfer.wallet.index',
                    'view' => 'wallet::customer.credit.transfer.wallet.check',
                ])->name('customer.credit.transfer.wallet.check')->block($lockSeconds = 30, $waitSeconds = 30);

                Route::post('credit/transfer/wallet/confirm', 'Gametech\Wallet\Http\Controllers\CreditTransferWalletController@confirm')->defaults('_config', [

                ])->name('customer.credit.transfer.wallet.confirm')->block($lockSeconds = 30, $waitSeconds = 30);

                Route::get('credit/transfer/wallet/complete', 'Gametech\Wallet\Http\Controllers\CreditTransferWalletController@complete')->defaults('_config', [
                    'view' => 'wallet::customer.credit.transfer.wallet.complete',
                ])->name('customer.credit.transfer.wallet.complete')->block($lockSeconds = 30, $waitSeconds = 30);

                // Transfer Wallet to Game
                Route::get('transfer/game', 'Gametech\Wallet\Http\Controllers\TransferGameController@index')->defaults('_config', [
                    'view' => 'wallet::customer.transfer.game.index',
                ])->name('customer.transfer.game.index');

                Route::post('transfer/game/check', 'Gametech\Wallet\Http\Controllers\TransferGameController@check')->defaults('_config', [
                    'redirect' => 'customer.transfer.game.index',
                    'view' => 'wallet::customer.transfer.game.check',
                ])->name('customer.transfer.game.check')->block($lockSeconds = 30, $waitSeconds = 30);

                Route::post('transfer/game/confirm', 'Gametech\Wallet\Http\Controllers\TransferGameController@confirm')->defaults('_config', [

                ])->name('customer.transfer.game.confirm')->block($lockSeconds = 30, $waitSeconds = 30);

                Route::get('transfer/game/complete', 'Gametech\Wallet\Http\Controllers\TransferGameController@complete')->defaults('_config', [
                    'view' => 'wallet::customer.transfer.game.complete',
                ])->name('customer.transfer.game.complete')->block($lockSeconds = 30, $waitSeconds = 30);

                // Transfer Game to Wallet
                Route::get('transfer/wallet', 'Gametech\Wallet\Http\Controllers\TransferWalletController@index')->defaults('_config', [
                    'view' => 'wallet::customer.transfer.wallet.index',
                ])->name('customer.transfer.wallet.index');

                Route::post('transfer/wallet/check', 'Gametech\Wallet\Http\Controllers\TransferWalletController@check')->defaults('_config', [
                    'redirect' => 'customer.transfer.wallet.index',
                    'view' => 'wallet::customer.transfer.wallet.check',
                ])->name('customer.transfer.wallet.check')->block($lockSeconds = 30, $waitSeconds = 30);

                Route::post('transfer/wallet/confirm', 'Gametech\Wallet\Http\Controllers\TransferWalletController@confirm')->defaults('_config', [

                ])->name('customer.transfer.wallet.confirm')->block($lockSeconds = 30, $waitSeconds = 30);

                Route::get('transfer/wallet/complete', 'Gametech\Wallet\Http\Controllers\TransferWalletController@complete')->defaults('_config', [
                    'view' => 'wallet::customer.transfer.wallet.complete',
                ])->name('customer.transfer.wallet.complete')->block($lockSeconds = 30, $waitSeconds = 30);


            });

        });

//        Route::fallback(\App\Http\Controllers\HomeController::class . '@index')
//            ->defaults('_config', [
//                'product_view' => 'wallet::customer.view',
//                'category_view' => 'wallet::customer.home.index'
//            ])
//            ->name('customer.productOrCategory.index');

    });

});
