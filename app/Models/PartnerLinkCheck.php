<?php

// v1.0 — 2026-06-08 | Partner backlink monitor — individual check record

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerLinkCheck extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'partner_site_id', 'checked_at', 'is_up', 'http_status',
        'response_time_ms', 'links_found', 'error_message',
    ];

    protected $casts = [
        'checked_at'      => 'datetime',
        'is_up'           => 'boolean',
        'links_found'     => 'array',
    ];

    public function partnerSite(): BelongsTo
    {
        return $this->belongsTo(PartnerSite::class);
    }

    /** Count of dofollow links in this check. */
    public function dofollowCount(): int
    {
        return count(array_filter($this->links_found ?? [], fn($l) => $l['is_dofollow']));
    }

    /** Count of nofollow links in this check. */
    public function nofollowCount(): int
    {
        return count(array_filter($this->links_found ?? [], fn($l) => !$l['is_dofollow']));
    }
}
