<?php

// v1.0 — 2026-11-23 | Reader-authored "listing blurb" for the Script Consulting page — a short
//                      header (e.g. "Animation specialist will take your script to next level")
//                      plus a paragraph, replacing the generic About-page bio that used to be
//                      shown there. Reader self-serve editable, admin-overridable, no approval
//                      queue (same as the note/call-mode fields it sits alongside).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->string('video_consulting_blurb_header')->nullable()->after('video_consulting_call_mode');
            $table->text('video_consulting_blurb')->nullable()->after('video_consulting_blurb_header');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn(['video_consulting_blurb_header', 'video_consulting_blurb']);
        });
    }
};
