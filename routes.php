<?php

use Illuminate\Support\Facades\Route;
use kolya2320\Ai_bot\Controllers\BotAIController;
use EvolutionCMS\Middleware\VerifyCsrfToken;

Route::prefix('botai')->middleware(
    VerifyCsrfToken::class
)->group(function () {
    Route::post('/send', [BotAIController::class, 'sendMessage']);
    Route::post('/history', [BotAIController::class, 'loadHistory']);
});