<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_sites', function (Blueprint $table) {
            $table->timestamp('coupon_eligible_at')->nullable()->after('coupon_uptime_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('partner_sites', function (Blueprint $table) {
            $table->dropColumn('coupon_eligible_at');
        });
    }
};
