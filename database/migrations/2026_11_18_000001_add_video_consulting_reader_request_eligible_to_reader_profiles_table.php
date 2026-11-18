<?php

// v1.0 — 2026-11-18 | Reader Request fee waiver (woo_script-coverage.php/
//                      woo_video-consulting.php in the theme repo) — whether a reader can be
//                      counted as one of a customer's requested readers when booking a video
//                      call with them. A plain reader always can (hardcoded in
//                      VideoConsultingAccess::eligibleForReaderRequestWaiver()); admin/editor
//                      can't by default, since neither writes coverage automatically — this
//                      column is the opt-in toggle for that case, defaulting to false so
//                      nothing changes until they turn it on for themselves.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->boolean('video_consulting_reader_request_eligible')->default(false)->after('video_consulting_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn('video_consulting_reader_request_eligible');
        });
    }
};
