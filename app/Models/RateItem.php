<?php

// v1.0 — 2026-07-11 | Admin-managed additional rate items shown on the Rates page

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RateItem extends Model
{
    protected $fillable = ['label', 'amount', 'sort_order'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }
}
