<?php

// v1.0 — 2026-11-07 | Video consulting Phase 2 — links an Assignment (assignment_type =
//                      Assignment::TYPE_VIDEO_CONSULTING) back to its booked slot and carries
//                      the Whereby room + recording/transcript once the call has happened.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->foreignId('video_consulting_hold_id')->nullable()->after('client_id')
                ->constrained('video_consulting_holds')->nullOnDelete();
            $table->string('whereby_room_url')->nullable()->after('video_consulting_hold_id');
            $table->string('whereby_room_name')->nullable()->after('whereby_room_url');
            $table->string('video_consulting_recording_path')->nullable()->after('whereby_room_name');
            $table->longText('video_consulting_transcript')->nullable()->after('video_consulting_recording_path');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('video_consulting_hold_id');
            $table->dropColumn([
                'whereby_room_url',
                'whereby_room_name',
                'video_consulting_recording_path',
                'video_consulting_transcript',
            ]);
        });
    }
};
