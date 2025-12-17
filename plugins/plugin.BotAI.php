<?php
namespace kolya2320\Ai_bot\plugins;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

$modx = evo();

Event::listen(['evolution.OnLoadSettings'], function () {
    if (!Schema::hasTable('botai_chats')) {
        Schema::create('botai_chats', function ($table) {
            $table->id();
            $table->string('session_id', 255);
            $table->text('user_message')->nullable();
            $table->text('bot_response')->nullable();
            $table->string('last_response_id', 255)->nullable();
            $table->timestamp('timestamp')->useCurrent();
            
            $table->index('session_id');
            $table->index('last_response_id');
            $table->index('timestamp');
        });
    }
    
    // Удаляем старую таблицу сессий если существует
    if (Schema::hasTable('botai_sessions')) {
        Schema::drop('botai_sessions');
    }
});

Event::listen(['evolution.OnLoadWebDocument'], function () use ($modx) { 
    $sessionId = $_COOKIE['botai_session'] ?? uniqid('botai_', true);
    
    if (!isset($_COOKIE['botai_session'])) {
        setcookie('botai_session', $sessionId, time() + (365 * 24 * 60 * 60), '/');
    }
    
    // Регистрируем CSS и JS
    $modx->regClientCSS('<link rel="stylesheet" href="' . MODX_SITE_URL . 'assets/plugins/BotAI/BotAI.css">');
    $modx->regClientScript('<script src="' . MODX_SITE_URL . 'assets/plugins/BotAI/BotAI.js" defer></script>');
    $modx->regClientHTMLBlock('<meta name="csrf-token" content="'.csrf_token().'">');
    
    // HTML структура чата
    $chatHTML = '    
<div class="chat-container">
    <div class="chat-button" id="chatButton">
        <span>💬</span>
    </div>
    
    <div class="chat-window" id="chatWindow">
        <div class="chat-header">
            <span>AI Ассистент</span>
            <button class="chat-close" id="chatClose">×</button>
        </div>
        
        <div class="chat-messages" id="chatMessages">
            <div class="message bot-message">
                <div class="message-content">Привет! Чем могу помочь?</div>
                <div class="message-time">00:00</div>
            </div>
        </div>
        
        <div class="chat-input">
            <textarea 
                id="chatInput" 
                placeholder="Введите сообщение..." 
                rows="1"
            ></textarea>
            <button id="chatSend">➤</button>
        </div>
    </div>
</div>';
    
    $modx->regClientHTMLBlock($chatHTML);
    $jsSession = '
<script>
window.botaiConfig = {
    sessionId: "' . $sessionId . '",
    baseUrl: "' . MODX_SITE_URL . '"
};
</script>';
    
    $modx->regClientHTMLBlock($jsSession);
});