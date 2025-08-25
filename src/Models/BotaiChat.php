<?php

namespace kolya2320\Ai_bot\Models;

use Illuminate\Database\Eloquent\Model;

class BotaiChat extends Model
{
    protected $table = 'botai_chats';
    public $timestamps = false;
    protected $fillable = [
        'session_id',
        'assistant_id',
        'thread_id',
        'user_message',
        'bot_response'
    ];
    
    public function session()
    {
        return $this->belongsTo(BotaiSession::class, 'session_id', 'session_id');
    }
}
