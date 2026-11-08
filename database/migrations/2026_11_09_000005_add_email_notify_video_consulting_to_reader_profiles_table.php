<?php

// v1.0 — 2026-11-09 | Video consulting — a reader who has opted into offering video
//                      consulting calls (i.e. has set a rate for at least one duration) must
//                      receive email notifications about them; this is enforced server-side
//                      in ProfileController::updateNotifications() regardless of what the
//                      reader submits, not just hidden/disabled client-side. Defaults to false
//                      so a reader who hasn't opted in sees it as an ordinary optional toggle.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->boolean('email_notify_video_consulting')->default(false)->after('email_notify_requests');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn('email_notify_video_consulting');
        });
    }
};
