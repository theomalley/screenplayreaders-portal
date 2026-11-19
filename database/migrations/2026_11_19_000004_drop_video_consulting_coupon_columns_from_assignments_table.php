<?php

// v1.0 — 2026-11-19 | Drops the coupon audit-trail columns added in
//                      2026_11_19_000002_add_coupon_columns_to_assignments_table — the reader
//                      discount-code feature was removed the same day it shipped, before any
//                      booking ever used one (confirmed 0 rows on production).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['video_consulting_coupon_code_used', 'video_consulting_discount_amount']);
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('video_consulting_coupon_code_used')->nullable()->after('video_consulting_customer_email');
            $table->decimal('video_consulting_discount_amount', 8, 2)->nullable()->after('video_consulting_coupon_code_used');
        });
    }
};
