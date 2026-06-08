<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_sites', function (Blueprint $table) {
            // Null = toggle per individual check; set to 0–100 to use rolling uptime window.
            $table->decimal('coupon_uptime_threshold', 5, 2)->nullable()->after('coupon_code');
        });
    }

    public function down(): void
    {
        Schema::table('partner_sites', function (Blueprint $table) {
            $table->dropColumn('coupon_uptime_threshold');
        });
    }
};
