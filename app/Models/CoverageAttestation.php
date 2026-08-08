<?php

// v1.0 — 2026-08-08 | Admin-managed quality attestation checkboxes for the SR coverage form

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoverageAttestation extends Model
{
    protected $fillable = ['text', 'sort_order'];
}
