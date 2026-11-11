<?php

// v1.0 — 2026-11-11 | Video consulting — dedupe Whereby recording.finished webhooks by the
//                      specific recordingId rather than "a recording path already exists", so an
//                      early/test recording can't permanently block the real call's recording
//                      from ever being processed. See DownloadWherebyRecording/
//                      WherebyWebhookController.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('video_consulting_last_recording_id')->nullable()->after('video_consulting_recording_path');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('video_consulting_last_recording_id');
        });
    }
};
