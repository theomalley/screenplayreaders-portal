<?php

// v1.0 — 2026-11-11 | Video consulting Phase 7 (partial) — call_token backs the portal-hosted
//                      "Join Call" page (public for the customer, authenticated for the reader)
//                      so neither party is ever handed the raw Whereby URL directly. The
//                      reminder_24h_sent_at column makes the 24h reminder sweep idempotent.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('video_consulting_call_token')->nullable()->unique()->after('whereby_room_name');
            $table->timestamp('video_consulting_reminder_24h_sent_at')->nullable()->after('video_consulting_call_token');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn([
                'video_consulting_call_token',
                'video_consulting_reminder_24h_sent_at',
            ]);
        });
    }
};
