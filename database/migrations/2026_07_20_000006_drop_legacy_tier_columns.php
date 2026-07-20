<?php

// 2026-07-20 | Drop the legacy hardcoded tier columns now that reader_profile_tier /
// assignment_tier fully replace them (backfilled by the preceding migration).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn(['tier_0', 'tier_1', 'tier_2']);
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('tier');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->boolean('tier_0')->default(false);
            $table->boolean('tier_1')->default(true);
            $table->boolean('tier_2')->default(false);
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->tinyInteger('tier')->default(1);
        });
    }
};
