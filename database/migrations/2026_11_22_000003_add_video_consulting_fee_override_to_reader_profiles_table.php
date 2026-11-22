<?php

// v1.0 — 2026-11-22 | Per-person override of the company's video-consulting fee — when
//                      enabled, replaces VideoConsultingDuration's own base_rate/
//                      base_rate_percent for this specific person (reader, editor, or admin)
//                      across every call length, instead of the sitewide default. Both the
//                      amount and percent can be 0, meaning the customer is charged exactly
//                      this person's own rate with no SR fee added. See
//                      VideoConsultingDuration::effectiveBaseRate().

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->boolean('video_consulting_fee_override_enabled')->default(false)->after('video_consulting_holiday_blackouts');
            $table->decimal('video_consulting_fee_override_amount', 8, 2)->nullable()->after('video_consulting_fee_override_enabled');
            $table->decimal('video_consulting_fee_override_percent', 5, 2)->nullable()->after('video_consulting_fee_override_amount');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'video_consulting_fee_override_enabled',
                'video_consulting_fee_override_amount',
                'video_consulting_fee_override_percent',
            ]);
        });
    }
};
