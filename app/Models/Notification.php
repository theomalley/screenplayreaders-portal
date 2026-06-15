<?php

// v1.0 — 2026-06-15 | Per-user Notification History — records dismissed/actioned dashboard alerts

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = ['user_id', 'title', 'body', 'url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function log(int $userId, string $title, ?string $body = null, ?string $url = null): void
    {
        static::create([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ]);
    }
}
