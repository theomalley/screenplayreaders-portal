<?php

// 2026-07-11 | Add tier_0 to reader_profiles — onboarding tier, mutually exclusive with tier_1/tier_2

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->boolean('tier_0')->default(false)->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn('tier_0');
        });
    }
};
