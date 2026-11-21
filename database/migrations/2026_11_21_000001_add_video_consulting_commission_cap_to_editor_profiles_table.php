<?php

// v1.0 — 2026-11-21 | Per-editor cap on how much commission they can earn on a single video
//                      consulting booking — EditorCommissionService::calculate() computes the
//                      video-consulting portion of an order's commission separately from every
//                      other product precisely so this cap can clamp it without affecting
//                      commission on anything else in the same order. Null = no cap (unchanged
//                      behavior). Edited on the editor's own profile page, "Editor Rates" section.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editor_profiles', function (Blueprint $table) {
            $table->decimal('video_consulting_commission_cap', 8, 2)->nullable()->after('editor_weekly_flat');
        });
    }

    public function down(): void
    {
        Schema::table('editor_profiles', function (Blueprint $table) {
            $table->dropColumn('video_consulting_commission_cap');
        });
    }
};
