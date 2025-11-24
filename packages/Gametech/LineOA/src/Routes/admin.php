<?php

use Gametech\LineOA\Http\Controllers\Admin\ChatController;
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

            Route::get('chat', [ChatController::class, 'page'])->name('chat');

            Route::get('conversations', [ChatController::class, 'index'])
                ->name('conversations.index');
            // => admin.line-oa.conversations.index

            Route::get('conversations/{conversation}', [ChatController::class, 'show'])
                ->name('conversations.show');
            // => admin.line-oa.conversations.show

            Route::post('conversations/{conversation}/reply', [ChatController::class, 'reply'])
                ->name('conversations.reply');
            // => admin.line-oa.conversations.reply

            Route::post('conversations/{conversation}/reply-image', [ChatController::class, 'replyImage'])
                ->name('conversations.reply_image');

            Route::get('messages/{message}/content', [
                ChatController::class, 'content',
            ])->name('messages.content');

            Route::get('members/find', [ChatController::class, 'findMember'])->name('members.find');

            Route::get('register/load-bank', [ChatController::class, 'loadBank'])->name('register.load-bank');

            Route::post('register/check-bank', [ChatController::class, 'checkBank'])->name('register.check-bank');

            Route::post('register/check-phone', [ChatController::class, 'checkPhone'])->name('register.check-phone');

            Route::post('contacts/{contact}/attach-member', [ChatController::class, 'attachMember'])
                ->name('contacts.attach-member');

            Route::post('conversations/{conversation}/accept', [ChatController::class, 'accept'])
                ->name('conversations.accept');
// => admin.line-oa.conversations.accept

            Route::post('conversations/{conversation}/lock', [ChatController::class, 'lock'])
                ->name('conversations.lock');
// => admin.line-oa.conversations.lock

            Route::post('conversations/{conversation}/unlock', [ChatController::class, 'unlock'])
                ->name('conversations.unlock');

            Route::post('conversations/{conversation}/close', [ChatController::class, 'close'])
                ->name('conversations.close');

            Route::post('conversations/{conversation}/open', [ChatController::class, 'open'])
                ->name('conversations.open');

            Route::post('conversations/{conversation}/cancel-register', [ChatController::class, 'cancelRegister'])
                ->name('conversations.cancel-register');

        });

});
