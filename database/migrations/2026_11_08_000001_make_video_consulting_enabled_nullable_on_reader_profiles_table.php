<?php

// v1.0 — 2026-11-08 | Video consulting — the per-reader kill switch becomes a true tri-state
//                      override: null = follow the sitewide setting, true/false = always
//                      enabled/disabled for that reader regardless of the sitewide setting.
//                      Previously it was AND'd with the global flag (both had to be true), so
//                      a reader could never be re-enabled once the global switch was off. See
//                      App\Support\VideoConsultingAccess::enabledFor().
//
//                      Every existing reader's `true` (the old default — nobody had actually
//                      set an override, they just hadn't been turned off) is reinterpreted as
//                      null so the global switch still actually hides everyone once this
//                      ships; an explicit `false` (admin had deliberately disabled that
//                      reader) stays a hard override.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->boolean('video_consulting_enabled')->nullable()->default(null)->change();
        });

        DB::table('reader_profiles')
            ->where('video_consulting_enabled', true)
            ->update(['video_consulting_enabled' => null]);
    }

    public function down(): void
    {
        DB::table('reader_profiles')
            ->whereNull('video_consulting_enabled')
            ->update(['video_consulting_enabled' => true]);

        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->boolean('video_consulting_enabled')->nullable(false)->default(true)->change();
        });
    }
};
