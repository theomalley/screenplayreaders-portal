<?php

// v1.0 — 2026-11-19 | Drops the reader discount-code columns added in
//                      2026_11_19_000001_add_video_consulting_listing_and_coupon_to_reader_profiles_table
//                      — the feature was removed the same day it shipped, before any reader set
//                      a code (confirmed 0 rows on production). video_consulting_note/
//                      video_consulting_call_mode are unaffected and stay.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            // The unique index on video_consulting_coupon_code must go first — SQLite (used in
            // tests) rebuilds the table to drop a column, and chokes if a unique index still
            // references it.
            $table->dropUnique(['video_consulting_coupon_code']);
        });

        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'video_consulting_coupon_code',
                'video_consulting_coupon_type',
                'video_consulting_coupon_amount',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->string('video_consulting_coupon_code')->nullable()->unique()->after('video_consulting_call_mode');
            $table->string('video_consulting_coupon_type')->nullable()->after('video_consulting_coupon_code');
            $table->decimal('video_consulting_coupon_amount', 8, 2)->nullable()->after('video_consulting_coupon_type');
        });
    }
};
