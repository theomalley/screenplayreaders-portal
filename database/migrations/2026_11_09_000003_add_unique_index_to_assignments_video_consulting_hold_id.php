<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Video consulting Phase 6 — the new POST /video-consulting/bookings endpoint converts a
 * VideoConsultingHold into an Assignment, following the same exists()-then-create() idempotency
 * shape as BudgetWebhookController. As with that controller, the exists()/converted_assignment_id
 * check alone isn't race-safe against two near-simultaneous requests for the same hold (a retried
 * request, a duplicate click) — this unique index is the real guarantee, and the controller
 * catches the resulting constraint violation. NULLs (every non-video-consulting assignment) do
 * not conflict with each other under a unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->unique('video_consulting_hold_id');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropUnique(['video_consulting_hold_id']);
        });
    }
};
