<?php

namespace kolya2320\Ai_bot\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BotaiSession extends Model
{
    protected $table = 'botai_sessions';
    public $timestamps = false;
    protected $fillable = [
        'session_id',
        'assistant_id', 
        'thread_id'
    ];

    /**
     * Отношение к чатам этой сессии
     */
    public function chats(): HasMany
    {
        return $this->hasMany(BotaiChat::class, 'session_id', 'session_id');
    }

    /**
     * Отношение к последнему чату сессии
     */
    public function latestChat(): HasOne
    {
        return $this->hasOne(BotaiChat::class, 'session_id', 'session_id')
                    ->orderBy('timestamp', 'desc')
                    ->orderBy('id', 'desc');
    }
}