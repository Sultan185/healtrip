<?php

use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\DirectoryController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\ChatPageController;
use App\Http\Middleware\EnsureConversationOwner;
use Illuminate\Support\Facades\Route;

Route::get('/', ChatPageController::class)->name('home');

/*
 * The JSON API. It lives in the web group on purpose: the only client is the
 * same-origin chat page, so requests are authenticated by the session cookie
 * and protected by CSRF, with no token to store in the browser.
 */
Route::prefix('api')->name('api.')->group(function () {
    Route::get('directory/doctors', [DirectoryController::class, 'doctors'])->name('directory.doctors');
    Route::get('directory/hospitals', [DirectoryController::class, 'hospitals'])->name('directory.hospitals');

    Route::post('conversations', [ConversationController::class, 'store'])
        ->middleware('throttle:conversations')
        ->name('conversations.store');

    Route::middleware(EnsureConversationOwner::class)->group(function () {
        Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');

        Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])
            ->middleware('throttle:chat')
            ->name('conversations.messages.store');
    });
});
