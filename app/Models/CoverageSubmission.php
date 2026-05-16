<?php

// v1.0 — 2026-05-16 | Stub — field spec TBD. See CLAUDE.md "Outstanding Decisions".

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoverageSubmission extends Model
{
    protected $fillable = [
        'assignment_id',
        // Fields added here once coverage form spec is finalised
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}
