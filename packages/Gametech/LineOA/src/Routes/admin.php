<?php

use Gametech\LineOa\Http\Controllers\Admin\ChatController;
use Illuminate\Support\Facades\Route;

// admin.xxx.com
Route::domain(
    config('app.admin_url').'.'.(
        is_null(config('app.admin_domain_url'))
            ? config('app.domain_url')
            : config('app.admin_domain_url')
    )
)->group(function () {

    Route::prefix('admin/line-oa')
        ->middleware(['web', 'admin'])
        ->as('admin.line-oa.') // 👈 ชื่อ route ทั้งกลุ่มขึ้นต้น admin.
        ->group(function () {

            Route::get('conversations', [ChatController::class, 'index'])
                ->name('conversations.index');
            // => admin.line-oa.conversations.index

            Route::get('conversations/{conversation}', [ChatController::class, 'show'])
                ->name('conversations.show');
            // => admin.line-oa.conversations.show

            Route::post('conversations/{conversation}/reply', [ChatController::class, 'reply'])
                ->name('conversations.reply');
            // => admin.line-oa.conversations.reply

        });

});
