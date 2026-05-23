<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpScoutConversation extends Model
{
    protected $table = 'helpscout_order_conversations';

    protected $fillable = [
        'order_number',
        'helpscout_conversation_id',
    ];
}
