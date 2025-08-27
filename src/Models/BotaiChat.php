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
        'assistant_id',
        'thread_id',
        'timestamp'
    ];

    /**
     * Отношение к сессии
     */
    public function session()
    {
        return $this->belongsTo(BotaiSession::class, 'session_id', 'session_id');
    }

    /**
     * Accessor для получения сообщения в зависимости от типа
     */
    public function getMessageAttribute()
    {
        return !empty($this->user_message) ? $this->user_message : $this->bot_response;
    }

    /**
     * Accessor для получения типа сообщения
     */
    public function getTypeAttribute()
    {
        return !empty($this->user_message) ? 'user' : 'bot';
    }
    protected $casts = [
        'timestamp' => 'datetime',
    ];
}