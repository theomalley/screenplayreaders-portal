<?php

// v1.0 — 2026-11-13 | AssemblyAI transcription + Claude contact-info-exchange check for
//                      video-consulting call recordings. video_consulting_transcription_id
//                      tracks the AssemblyAI job so its webhook (which only carries
//                      transcript_id + status, no transcript text) can be matched back to the
//                      right assignment. The flag/evidence columns surface Claude's verdict on
//                      the QC "Needs Attention" review — a human still decides, this only flags.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('video_consulting_transcription_id')->nullable()->after('video_consulting_transcript')->index();
            $table->boolean('video_consulting_contact_info_flag')->nullable()->after('video_consulting_transcription_id');
            $table->text('video_consulting_contact_info_evidence')->nullable()->after('video_consulting_contact_info_flag');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn([
                'video_consulting_transcription_id',
                'video_consulting_contact_info_flag',
                'video_consulting_contact_info_evidence',
            ]);
        });
    }
};
