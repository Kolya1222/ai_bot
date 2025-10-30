<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use kolya2320\Ai_bot\Controllers\BotAIController;
use kolya2320\Ai_bot\Controllers\McpServerController;
use Illuminate\Support\Facades\Log;

// Маршруты для AI ассистента
Route::prefix('bot-ai')->group(function () {
    Route::post('/send-message', [BotAIController::class, 'sendMessage'])->name('botai.send_message');
    Route::post('/load-history', [BotAIController::class, 'loadHistory'])->name('botai.load_history');
});

// MCP Server routes - ОСНОВНОЙ ENDPOINT ДЛЯ YANDEX
Route::match(['GET', 'POST', 'OPTIONS'], '/api/mcp', [McpServerController::class, 'handleRequest']);