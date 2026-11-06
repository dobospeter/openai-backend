<?php

declare(strict_types=1);

use App\Api\Conversation\Controllers\ConversationController;
use App\Api\Conversation\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

// Registered under the /api prefix in bootstrap/app.php.

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {
    Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::post('conversations', [ConversationController::class, 'store'])->name('conversations.store');

    Route::prefix('conversations/{conversation}')->whereUuid(['conversation', 'message'])->group(function (): void {
        Route::get('/', [ConversationController::class, 'show'])->name('conversations.show');
        Route::put('/', [ConversationController::class, 'update'])->name('conversations.update');
        Route::delete('/', [ConversationController::class, 'destroy'])->name('conversations.destroy');

        Route::get('messages', [MessageController::class, 'index'])->name('conversations.messages.index');
        Route::post('messages', [MessageController::class, 'store'])
            ->middleware('throttle:messages')
            ->name('conversations.messages.store');
        Route::get('messages/{message}', [MessageController::class, 'show'])->name('conversations.messages.show');
        Route::delete('messages/{message}', [MessageController::class, 'destroy'])
            ->name('conversations.messages.destroy');
    });
});
