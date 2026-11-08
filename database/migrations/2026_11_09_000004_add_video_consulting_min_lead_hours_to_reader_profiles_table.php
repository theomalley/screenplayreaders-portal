<?php

// v1.0 — 2026-11-09 | Video consulting — reader-set minimum notice (in hours) required
//                      before a call, so they have time to read the script and compile
//                      notes. Defaults to 0 (no minimum beyond "must be in the future") so
//                      existing readers aren't suddenly blocked out until they set one.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->unsignedInteger('video_consulting_min_lead_hours')->default(0)->after('video_consulting_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn('video_consulting_min_lead_hours');
        });
    }
};
