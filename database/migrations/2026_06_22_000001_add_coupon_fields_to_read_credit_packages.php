<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('read_credit_packages', function (Blueprint $table) {
            $table->string('coupon_code', 50)->nullable()->after('status');
            $table->unsignedSmallInteger('credits_at_expiry')->nullable()->after('coupon_code');
        });
    }

    public function down(): void
    {
        Schema::table('read_credit_packages', function (Blueprint $table) {
            $table->dropColumn(['coupon_code', 'credits_at_expiry']);
        });
    }
};
