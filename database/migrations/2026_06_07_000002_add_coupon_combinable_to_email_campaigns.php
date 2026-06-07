<?php

// v1.0 — 2026-06-07 | Allow per-campaign control of WooCommerce coupon individual_use flag

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->boolean('coupon_combinable')->default(false)->after('coupon_product_ids');
        });
    }

    public function down(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->dropColumn('coupon_combinable');
        });
    }
};
