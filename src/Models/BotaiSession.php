<?php

namespace kolya2320\Ai_bot\Models;

use Illuminate\Database\Eloquent\Model;

class BotaiSession extends Model
{
    protected $table = 'botai_sessions';
    
    protected $fillable = [
        'session_id',
        'assistant_id', 
        'thread_id'
    ];
}
