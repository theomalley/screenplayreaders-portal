<?php

// v1.0 — 2026-11-07 | Video consulting Phase 1 — one-off exceptions to a reader's recurring
//                      ReaderAvailabilityRule windows (vacation, a single blocked afternoon,
//                      etc). start_at/end_at rather than a bare date so a blackout can cover
//                      part of a day. Self-serve, see VideoConsultingAvailabilityController.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reader_blackout_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reader_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['reader_id', 'start_at', 'end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reader_blackout_dates');
    }
};
