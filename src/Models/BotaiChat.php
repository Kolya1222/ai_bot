<?php

namespace kolya2320\Ai_bot\Models;

use Illuminate\Database\Eloquent\Model;

class BotaiChat extends Model
{
    protected $table = 'botai_chats';
    public $timestamps = false;
    protected $fillable = [
        'session_id',
        'user_message',
        'bot_response',
        'last_response_id',
        'timestamp'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];

    /**
     * Получить последний response_id для сессии
     */
    public static function getLastResponseId(string $sessionId): ?string
    {
        $lastMessage = self::where('session_id', $sessionId)
            ->whereNotNull('last_response_id')
            ->orderBy('timestamp', 'desc')
            ->first();
            
        return $lastMessage ? $lastMessage->last_response_id : null;
    }

    /**
     * Получить историю чата для сессии
     */
    public static function getHistory(string $sessionId, int $limit = 50)
    {
        return self::where('session_id', $sessionId)
            ->orderBy('timestamp', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Сохранить сообщение пользователя
     */
    public static function saveUserMessage(string $sessionId, string $message): self
    {
        return self::create([
            'session_id' => $sessionId,
            'user_message' => $message,
            'bot_response' => null,
            'last_response_id' => null,
            'timestamp' => now()
        ]);
    }

    /**
     * Сохранить ответ бота
     */
    public static function saveBotResponse(string $sessionId, string $response, string $responseId): self
    {
        return self::create([
            'session_id' => $sessionId,
            'user_message' => null,
            'bot_response' => $response,
            'last_response_id' => $responseId,
            'timestamp' => now()
        ]);
    }
}