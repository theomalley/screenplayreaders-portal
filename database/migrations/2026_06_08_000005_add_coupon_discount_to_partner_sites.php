<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_sites', function (Blueprint $table) {
            $table->string('coupon_discount_type')->nullable()->default('percent')->after('coupon_uptime_threshold');
            $table->decimal('coupon_amount', 10, 2)->nullable()->after('coupon_discount_type');
        });
    }

    public function down(): void
    {
        Schema::table('partner_sites', function (Blueprint $table) {
            $table->dropColumn(['coupon_discount_type', 'coupon_amount']);
        });
    }
};
