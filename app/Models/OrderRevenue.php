<?php

// v1.2 — 2026-05-25 | Add customer/order detail columns for Order Log
// v1.1 — 2026-05-25 | Add editor_paid_at and line_items_json
// v1.0 — 2026-05-25 | Initial — WooCommerce order financials synced from theme via webhook

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderRevenue extends Model
{
    protected $fillable = [
        'order_number',
        'woocommerce_order_id',
        'ordered_at',
        'order_total',
        'discount_amount',
        'cog_reader',
        'cog_processing',
        'cog_precommission',
        'cog_commission',
        'cog_total',
        'net_revenue',
        'payment_method',
        'coupon_code',
        'customer_email',
        'customer_name',
        'customer_phone',
        'customer_address',
        'script_title',
        'sku',
        'ticket_summary',
        'order_quantity',
        'invoice_number',
        'services_purchased',
        'line_items_json',
        'staff_member',
        'skip_commission',
        'editor_paid_at',
    ];

    protected function casts(): array
    {
        return [
            'ordered_at'        => 'datetime',
            'order_total'       => 'decimal:2',
            'discount_amount'   => 'decimal:2',
            'cog_reader'        => 'decimal:2',
            'cog_processing'    => 'decimal:2',
            'cog_precommission' => 'decimal:2',
            'cog_commission'    => 'decimal:2',
            'cog_total'         => 'decimal:2',
            'net_revenue'       => 'decimal:2',
            'skip_commission'   => 'boolean',
            'editor_paid_at'    => 'datetime',
        ];
    }
}
