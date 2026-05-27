<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementRead extends Model
{
    protected $fillable = ['announcement_id', 'user_id', 'read_at', 'dismissed_at'];

    protected $casts = [
        'read_at'      => 'datetime',
        'dismissed_at' => 'datetime',
    ];
}
