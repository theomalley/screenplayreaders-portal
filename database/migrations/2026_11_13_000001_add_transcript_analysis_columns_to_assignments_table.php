<?php

// v1.0 — 2026-11-13 | AssemblyAI transcription for video-consulting call recordings.
//                      video_consulting_transcription_id tracks the AssemblyAI job so its
//                      webhook (which only carries transcript_id + status, no transcript text)
//                      can be matched back to the right assignment. The transcript itself is
//                      reviewed by a human in QC — no automated flagging.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('video_consulting_transcription_id')->nullable()->after('video_consulting_transcript')->index();
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['video_consulting_transcription_id']);
        });
    }
};
