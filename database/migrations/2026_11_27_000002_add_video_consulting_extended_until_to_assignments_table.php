<?php

// v1.0 — 2026-11-27 | Reader-initiated call extension. The original VideoConsultingHold::
//                      slot_end stays the scheduling/payroll source of truth; this column only
//                      tracks a live-extended effective end so the customer's independently
//                      loaded call page can poll and pick up an extension the reader made.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->timestamp('video_consulting_extended_until')->nullable()->after('video_consulting_reminder_24h_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('video_consulting_extended_until');
        });
    }
};
