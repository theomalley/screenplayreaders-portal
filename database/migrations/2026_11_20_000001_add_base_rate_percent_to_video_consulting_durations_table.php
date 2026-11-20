<?php

// v1.0 — 2026-11-20 | Lets the company's cut scale with a reader's own rate instead of staying a
//                      flat dollar amount — useful once reader rates can go as high as $5000 and a
//                      $100 flat base rate stops making sense. `base_rate` becomes the floor (the
//                      minimum the company ever keeps); `base_rate_percent` (0-100, default 0 so
//                      nothing changes for existing durations) is a percentage of the reader's own
//                      rate, and the effective base rate is whichever of the two is higher — see
//                      VideoConsultingDuration::effectiveBaseRate().

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_consulting_durations', function (Blueprint $table) {
            $table->decimal('base_rate_percent', 5, 2)->default(0)->after('base_rate');
        });
    }

    public function down(): void
    {
        Schema::table('video_consulting_durations', function (Blueprint $table) {
            $table->dropColumn('base_rate_percent');
        });
    }
};
