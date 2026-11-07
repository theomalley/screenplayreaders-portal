<?php

// v1.0 — 2026-11-07 | Video consulting Phase 1 — short-TTL slot reservation placed by the
//                      theme's AJAX add-to-cart, to prevent two customers double-booking the
//                      same reader/slot before checkout completes. The unique index on
//                      (reader_id, slot_start) is the real, race-safe guarantee — mirrors the
//                      unique-index-plus-QueryException-catch pattern added for budget_orders
//                      (2026_09_09_000002 / BudgetWebhookController::store()). Expired, unconverted
//                      holds are swept by video-consulting:release-expired-holds
//                      (app/Console/Commands/ReleaseExpiredVideoConsultingHolds.php).
//                      converted_assignment_id is wired up for real once order-completed webhook
//                      -> Assignment creation exists (Phase 6) — nullable/unused until then.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_consulting_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reader_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('duration_id')->constrained('video_consulting_durations')->restrictOnDelete();
            $table->dateTime('slot_start');
            $table->dateTime('slot_end');
            $table->string('hold_token', 64)->unique();
            $table->string('status')->default('held');
            $table->dateTime('expires_at');
            $table->foreignId('converted_assignment_id')->nullable()->constrained('assignments')->nullOnDelete();
            $table->timestamps();

            $table->unique(['reader_id', 'slot_start']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_consulting_holds');
    }
};
