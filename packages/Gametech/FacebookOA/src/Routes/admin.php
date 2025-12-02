<?php

use Gametech\FacebookOA\Http\Controllers\Admin\ChatController;
use Gametech\FacebookOA\Http\Controllers\Admin\FacebookAccountController;
use Gametech\FacebookOA\Http\Controllers\Admin\FacebookQuickReplyController;
use Gametech\FacebookOA\Http\Controllers\Admin\FacebookTemplateController;
use Illuminate\Support\Facades\Route;

// admin.xxx.com
Route::domain(
    config('app.admin_url').'.'.(
        is_null(config('app.admin_domain_url'))
            ? config('app.domain_url')
            : config('app.admin_domain_url')
    )
)->group(function () {

    Route::group(['middleware' => ['web', 'admin', 'auth', '2fa']], function () {
        Route::prefix('facebook-oa')
            ->as('admin.facebook-oa.') // 👈 ชื่อ route ทั้งกลุ่มขึ้นต้น admin.
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

                Route::get('messages/{message}/content', [ChatController::class, 'content',
                ])->name('messages.content');

                Route::get('conversations/{conversation}/quick-replies', [ChatController::class, 'quickReplies'])
                    ->name('conversations.quick-replies');

                Route::post('conversations/{conversation}/reply-template', [ChatController::class, 'replyTemplate'])
                    ->name('conversations.reply-template');

                Route::get('members/find', [ChatController::class, 'findMember'])->name('members.find');

                Route::get('register/load-bank', [ChatController::class, 'loadBank'])->name('register.load-bank');

                Route::post('register/check-bank', [ChatController::class, 'checkBank'])->name('register.check-bank');

                Route::post('register/check-phone', [ChatController::class, 'checkPhone'])->name('register.check-phone');

                Route::post('register/member', [ChatController::class, 'registerMember'])->name('register.member');

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

                Route::get('get-balance', [ChatController::class, 'getBalance'])
                    ->name('getbalance');
            });

        Route::prefix('facebook_account')->group(function () {
            Route::get('/', [FacebookAccountController::class, 'index'])->defaults('_config', [
                'view' => 'admin::module.facebook_account.index',
            ])->name('admin.facebook_account.index');
            Route::post('create', [FacebookAccountController::class, 'create'])->name('admin.facebook_account.create');
            Route::post('loaddata', [FacebookAccountController::class, 'loadData'])->name('admin.facebook_account.loaddata');
            Route::post('edit', [FacebookAccountController::class, 'edit'])->name('admin.facebook_account.edit');
            Route::post('update/{id?}', [FacebookAccountController::class, 'update'])->name('admin.facebook_account.update');
            Route::post('delete', [FacebookAccountController::class, 'destroy'])->name('admin.facebook_account.delete');
        });

        Route::prefix('facebook_template')->group(function () {
            Route::get('/', [FacebookTemplateController::class, 'index'])->defaults('_config', [
                'view' => 'admin::module.facebook_template.index',
            ])->name('admin.facebook_template.index');
            Route::post('create', [FacebookTemplateController::class, 'create'])->name('admin.facebook_template.create');
            Route::post('loaddata', [FacebookTemplateController::class, 'loadData'])->name('admin.facebook_template.loaddata');
            Route::post('edit', [FacebookTemplateController::class, 'edit'])->name('admin.facebook_template.edit');
            Route::post('update/{id?}', [FacebookTemplateController::class, 'update'])->name('admin.facebook_template.update');
            Route::post('delete', [FacebookTemplateController::class, 'destroy'])->name('admin.facebook_template.delete');
        });

        Route::prefix('facebook_quick_reply')->group(function () {
            Route::get('/', [FacebookQuickReplyController::class, 'index'])->defaults('_config', [
                'view' => 'admin::module.facebook_quick_reply.index',
            ])->name('admin.facebook_quick_reply.index');
            Route::post('create', [FacebookQuickReplyController::class, 'create'])->name('admin.facebook_quick_reply.create');
            Route::post('loaddata', [FacebookQuickReplyController::class, 'loadData'])->name('admin.facebook_quick_reply.loaddata');
            Route::post('edit', [FacebookQuickReplyController::class, 'edit'])->name('admin.facebook_quick_reply.edit');
            Route::post('update/{id?}', [FacebookQuickReplyController::class, 'update'])->name('admin.facebook_quick_reply.update');
            Route::post('delete', [FacebookQuickReplyController::class, 'destroy'])->name('admin.facebook_quick_reply.delete');
        });

    });
});
