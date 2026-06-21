<?php

// v1.0 — 2026-06-21 | Initial: tracks budget generation orders from WooCommerce through calculation and delivery

namespace App\Models\Budget;

use Illuminate\Database\Eloquent\Model;

class BudgetOrder extends Model
{
    protected $table = 'budget_orders';

    protected $fillable = [
        'woo_order_id',
        'customer_name',
        'customer_email',
        'form_entry_id',
        'budget_amount',
        'budget_class',
        'state',
        'guild_wga',
        'guild_dga',
        'guild_sag',
        'guild_iatse',
        'guild_teamsters',
        'sag_student',
        'sag_short',
        'weeks_prep',
        'weeks_shoot',
        'weeks_wrap',
        'weeks_post',
        'use_time_defaults',
        'cast_size',
        'cast_data',
        'surplus_cast',
        'surplus_stunts',
        'surplus_travel',
        'surplus_spfx',
        'surplus_mufx',
        'surplus_animals',
        'surplus_vfx',
        'header_data',
        'form_input_data',
        'payload_json',
        'topsheet_only',
        'drive_spreadsheet_id',
        'drive_pdf_id',
        'drive_xlsx_id',
        'status',
        'error_message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'budget_amount' => 'decimal:2',
            'budget_class' => 'integer',
            'guild_wga' => 'boolean',
            'guild_dga' => 'boolean',
            'guild_sag' => 'boolean',
            'guild_iatse' => 'boolean',
            'guild_teamsters' => 'boolean',
            'sag_student' => 'boolean',
            'sag_short' => 'boolean',
            'weeks_prep' => 'decimal:1',
            'weeks_shoot' => 'decimal:1',
            'weeks_wrap' => 'decimal:1',
            'weeks_post' => 'decimal:1',
            'use_time_defaults' => 'boolean',
            'cast_size' => 'integer',
            'cast_data' => 'array',
            'header_data' => 'array',
            'form_input_data' => 'array',
            'payload_json' => 'array',
            'topsheet_only' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
}
