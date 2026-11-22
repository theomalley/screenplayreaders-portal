<?php

// v1.0 — 2026-11-22 | Reader self-serve "Available for video calls" pause toggle. Distinct
//                      from video_consulting_enabled (the admin-only tri-state policy
//                      override) and the sitewide Setting kill switch — turning this off just
//                      stops the reader from being offered to customers right now; it leaves
//                      their weekly schedule (ReaderAvailabilityRule rows) and
//                      video_consulting_min_lead_hours completely untouched, so turning it
//                      back on restores availability exactly as it was. Defaults to true so no
//                      existing reader is silently paused. See
//                      App\Support\VideoConsultingAccess::bookableFor().

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->boolean('video_consulting_available')->default(true)->after('video_consulting_min_lead_hours');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn('video_consulting_available');
        });
    }
};
