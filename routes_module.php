<?php

use kolya2320\Ai_bot\Controllers\BotAIManagerController;
use Illuminate\Support\Facades\Route;
// Главная страница менеджера
Route::get('', [BotAIManagerController::class, 'index'])->name('ai-bot.manager.index');
// API endpoints
Route::prefix('api')->group(function () {
    Route::get('/sessions', [BotAIManagerController::class, 'getSessions'])->name('ai-bot.sessions.list');
    Route::get('/sessions/{sessionId}', [BotAIManagerController::class, 'getSessionDetail'])->name('ai-bot.sessions.detail');
    Route::get('/search', [BotAIManagerController::class, 'searchMessages'])->name('ai-bot.messages.search');
    Route::get('/statistics', [BotAIManagerController::class, 'getStatistics'])->name('ai-bot.statistics');
    Route::post('/sessions/{sessionId}', [BotAIManagerController::class, 'deleteSession'])->name('ai-bot.sessions.delete');
});