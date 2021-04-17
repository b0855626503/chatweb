<?php

Route::domain(config('app.admin_url') . '.' . config('app.domain_url'))->group(function () {

    Route::prefix('api')->group(function () {


        Route::group(['namespace' => 'Gametech\API\Http\Controllers', 'middleware' => ['api']], function () {

            Route::post('announce', 'AnnounceController@Announce');

            Route::post('krungsri/insertstatement.php', 'BankPaymentController@krungsri');

//        Route::prefix('bank')->group(function () {
//            Route::post('krungsri/insertstatement.php', 'BankPaymentController@krungsri');
//        });

        });
    });
});
