<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpScoutWebhookLog extends Model
{
    protected $table = 'helpscout_webhook_logs';

    protected $fillable = [
        'event',
        'helpscout_conversation_id',
        'payload',
        'signature_valid',
    ];

    protected $casts = [
        'payload'         => 'array',
        'signature_valid' => 'boolean',
    ];
}
