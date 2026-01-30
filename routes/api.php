<?php

use App\Http\Controllers\Api\OpenAIController;
use Illuminate\Support\Facades\Route;

Route::prefix('openai')->group(function () {
    Route::post('/message', [OpenAIController::class, 'sendMessage']);
    Route::get('/conversation/{conversationId}', [OpenAIController::class, 'getConversation']);
    Route::get('/conversations', [OpenAIController::class, 'listConversations']);
});
