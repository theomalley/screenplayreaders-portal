<?php

// v1.0 — 2026-06-06 | Reusable email HTML template

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = ['name', 'html'];
}
