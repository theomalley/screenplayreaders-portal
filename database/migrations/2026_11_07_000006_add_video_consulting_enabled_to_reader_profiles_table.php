<?php

// v1.0 — 2026-11-07 | Video consulting Phase 1 — admin-only per-reader kill switch. Defaults
//                      to enabled; combined with the sitewide 'video_consulting_enabled'
//                      Setting (both must be on) via App\Support\VideoConsultingAccess.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->boolean('video_consulting_enabled')->default(true)->after('watermark_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn('video_consulting_enabled');
        });
    }
};
