<?php

// 2026-06-05 | Add tier_1/tier_2 to reader_profiles; add tier to assignments

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->boolean('tier_1')->default(true)->after('user_id');
            $table->boolean('tier_2')->default(false)->after('tier_1');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->tinyInteger('tier')->default(1)->after('assignment_type');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn(['tier_1', 'tier_2']);
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('tier');
        });
    }
};
