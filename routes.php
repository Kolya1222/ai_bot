<?php

use Illuminate\Support\Facades\Route;
use kolya2320\Ai_bot\Controllers\BotAIController;
// Маршруты для AI ассистента
Route::prefix('bot-ai')->group(function () {
    Route::post('/send-message', [BotAIController::class, 'sendMessage'])->name('botai.send_message');
    Route::post('/load-history', [BotAIController::class, 'loadHistory'])->name('botai.load_history');
    Route::post('/create-assistant', [BotAIController::class, 'createAssistant'])->name('botai.create_assistant');
});