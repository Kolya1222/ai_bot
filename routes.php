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
    Route::post('/create-assistant', [BotAIController::class, 'createAssistant'])->name('botai.create_assistant');
});

Route::post('/api/mcp', [McpServerController::class, 'handleRequest']);

Route::get('/debug-storage', function() {
    // Проверим пути
    $storagePath = defined('EVO_STORAGE_PATH') ? EVO_STORAGE_PATH : storage_path();
    $logPath = $storagePath . 'logs/laravel.log';
    
    $info = [
        'EVO_STORAGE_PATH' => EVO_STORAGE_PATH ?? 'Not defined',
        'storage_path()' => storage_path(),
        'log_path' => $logPath,
        'log_file_exists' => file_exists($logPath),
        'logs_dir_exists' => file_exists($storagePath . 'logs'),
        'logs_dir_writable' => is_writable($storagePath . 'logs'),
        'php_user' => get_current_user(),
    ];
    
    // Если папка logs существует, покажем ее содержимое
    if (file_exists($storagePath . 'logs')) {
        $info['logs_directory_contents'] = scandir($storagePath . 'logs');
    }
    
    return response()->json($info);
});

Route::post('/api/mcp-test', function(Request $request) {
    Log::info('MCP TEST endpoint called', ['content' => $request->getContent()]);
    
    return response()->json([
        'status' => 'success',
        'message' => 'MCP test endpoint is working',
        'timestamp' => now()->toISOString()
    ]);
});