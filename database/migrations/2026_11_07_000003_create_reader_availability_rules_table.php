<?php

// v1.0 — 2026-11-07 | Video consulting Phase 1 — a reader's recurring weekly availability
//                      windows for consulting calls (distinct from ReaderProfile::availability,
//                      which is just a binary available/unavailable toggle for regular coverage
//                      assignments). Self-serve, see VideoConsultingAvailabilityController.
//                      VideoConsultingAvailabilityService turns these, minus blackout dates and
//                      active holds, into actual bookable slots.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reader_availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reader_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0 (Sunday) - 6 (Saturday)
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['reader_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reader_availability_rules');
    }
};
