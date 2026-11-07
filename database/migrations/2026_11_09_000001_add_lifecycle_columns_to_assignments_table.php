<?php

// v1.0 — 2026-11-09 | Video consulting Phase 3 — cancellation/no-show/reschedule. Reuses the
//                      existing generic cancellation_reason column (added 2026-06-17) rather
//                      than a video-consulting-specific one.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('video_consulting_customer_email')->nullable()->after('video_consulting_transcript');
            $table->string('video_consulting_needs_attention_reason')->nullable()->after('video_consulting_customer_email');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['video_consulting_customer_email', 'video_consulting_needs_attention_reason']);
        });
    }
};
