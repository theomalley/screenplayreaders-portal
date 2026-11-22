<?php

// v1.0 — 2026-11-22 | Per-reader US federal holiday blackouts for video consulting calls —
//                      nullable JSON array of App\Support\FederalHolidays keys currently
//                      blacked out. Null (never saved) is treated as "every holiday" by every
//                      reader — see ReaderProfile and VideoConsultingAvailabilityService::
//                      generateSlots() — so a reader who never opens the accordion is still
//                      blacked out on all of them by default.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->json('video_consulting_holiday_blackouts')->nullable()->after('video_consulting_available');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn('video_consulting_holiday_blackouts');
        });
    }
};
