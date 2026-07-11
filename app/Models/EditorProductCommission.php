<?php

// v1.1 — 2026-06-23 | allProducts(): merge hardcoded PRODUCTS with admin-added custom products from settings
// v1.0 — 2026-05-25 | Per-product commission config for an editor
//   commission_enabled = false → product never earns commission for this editor
//   custom_amount set → fixed dollar commission per order line occurrence
//   custom_amount null → use this editor's commission rate % × eligible precommission share

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Setting;

class EditorProductCommission extends Model
{
    // Full WooCommerce product catalog pulled from woo_order-financials.php
    // Keys are WooCommerce variation_id (or product_id for simple products)
    public const PRODUCTS = [
        54911 => ['label' => '1 Reader',          'commission' => true],
        54912 => ['label' => '2 Readers',          'commission' => true],
        54913 => ['label' => '3 Readers',          'commission' => true],
        54923 => ['label' => 'Oversized 1R',       'commission' => true],
        54926 => ['label' => 'Oversized 2R',       'commission' => true],
        54930 => ['label' => 'Oversized 3R',       'commission' => true],
        54937 => ['label' => 'Rush 1 Reader',      'commission' => true],
        54938 => ['label' => 'Rush 2 Readers',     'commission' => true],
        54939 => ['label' => 'Rush 3 Readers',     'commission' => true],
        54944 => ['label' => 'Request 1 Reader',   'commission' => true],
        54945 => ['label' => 'Request 2 Readers',  'commission' => true],
        54946 => ['label' => 'Request 3 Readers',  'commission' => true],
        53161 => ['label' => 'Vaulting',           'commission' => true],
        5868  => ['label' => 'Advanced Script Coverage', 'commission' => true],
        5871  => ['label' => 'Short Coverage',     'commission' => true],
        23425 => ['label' => 'Proofreading',       'commission' => false],
        5872  => ['label' => 'Formatting',         'commission' => false],
        55561 => ['label' => 'Reg 90-Day',         'commission' => false],
        55562 => ['label' => 'Reg 5-Year',         'commission' => false],
        55563 => ['label' => 'Reg 10-Year',        'commission' => false],
        22965 => ['label' => 'Consultation',       'commission' => false],
        55672 => ['label' => 'Film Budget',        'commission' => false],
    ];

    public static function allProducts(): array
    {
        $custom = json_decode(Setting::getValue('commission_custom_products', '[]'), true) ?: [];
        $merged = self::PRODUCTS;
        foreach ($custom as $p) {
            $merged[(int) $p['id']] = ['label' => $p['label'], 'commission' => (bool) ($p['commission'] ?? false)];
        }
        return $merged;
    }

    protected $fillable = [
        'editor_profile_id',
        'woo_product_id',
        'product_label',
        'commission_enabled',
        'custom_amount',
    ];

    protected function casts(): array
    {
        return [
            'commission_enabled' => 'boolean',
            'custom_amount'      => 'decimal:2',
            'woo_product_id'     => 'integer',
        ];
    }

    public function editorProfile(): BelongsTo
    {
        return $this->belongsTo(EditorProfile::class);
    }
}
