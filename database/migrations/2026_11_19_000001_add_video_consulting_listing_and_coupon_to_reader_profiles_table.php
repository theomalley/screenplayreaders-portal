<?php

// v1.0 — 2026-11-19 | Reader-authored video-consulting listing extras: a free-text note shown
//                      on the public listing even when the reader currently has no open slots
//                      (not shown at all if they've fully disabled video consulting — unchanged
//                      behavior there), a video-vs-audio-only call mode, and the reader's own
//                      single reusable discount code for their video consulting service. The
//                      code's type/amount are only validated for sanity here (amount >= 0,
//                      percent <= 100) — the real safety guarantee (never discounting the
//                      house's base_rate, never dropping the reader's own cut below $0) is
//                      enforced at redemption time in VideoConsultingAccess, not here, since
//                      reader_rate differs per duration and can't be checked once at save time.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->text('video_consulting_note')->nullable()->after('video_consulting_reader_request_eligible');
            $table->string('video_consulting_call_mode')->default('video')->after('video_consulting_note');
            $table->string('video_consulting_coupon_code')->nullable()->unique()->after('video_consulting_call_mode');
            $table->string('video_consulting_coupon_type')->nullable()->after('video_consulting_coupon_code');
            $table->decimal('video_consulting_coupon_amount', 8, 2)->nullable()->after('video_consulting_coupon_type');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'video_consulting_note',
                'video_consulting_call_mode',
                'video_consulting_coupon_code',
                'video_consulting_coupon_type',
                'video_consulting_coupon_amount',
            ]);
        });
    }
};
