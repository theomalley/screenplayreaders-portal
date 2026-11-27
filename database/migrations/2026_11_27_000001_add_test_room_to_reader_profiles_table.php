<?php

// v1.0 — 2026-11-27 | Add test_room_url/test_room_name/test_room_expires_at — a persistent
//                      Whereby room each reader/admin/editor taking video calls can open any
//                      time to check their camera/mic, separate from any real booking. See
//                      VideoConsultingCallService::getOrCreateTestRoomUrl().

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->string('test_room_url')->nullable()->after('video_consulting_listing_rejection_note');
            $table->string('test_room_name')->nullable()->after('test_room_url');
            $table->timestamp('test_room_expires_at')->nullable()->after('test_room_name');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn(['test_room_url', 'test_room_name', 'test_room_expires_at']);
        });
    }
};
