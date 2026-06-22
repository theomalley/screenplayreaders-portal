<?php

// v1.0 — 2026-06-22 | Audit log for read credit package events: redemptions, admin adjustments, expiration, coupon creation

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadCreditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'read_credit_package_id',
        'event_type',
        'credits_before',
        'credits_after',
        'note',
        'script_title',
        'order_number',
        'performed_by',
        'created_at',
    ];

    protected $casts = [
        'credits_before' => 'integer',
        'credits_after'  => 'integer',
        'created_at'     => 'datetime',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(ReadCreditPackage::class, 'read_credit_package_id');
    }
}
