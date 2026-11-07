<?php

// v1.0 — 2026-11-07 | Video consulting Phase 1 — each reader opts in and sets their own pay
//                      rate per duration independently (self-serve, see
//                      VideoConsultingRateController); no formula/multiplier across durations.
//                      Rate is validated against the duration's min_reader_rate/max_reader_rate
//                      at write time. duration_id is restrict-on-delete so a duration with any
//                      rate on file can never be hard-deleted, only soft-disabled.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reader_video_consulting_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reader_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('duration_id')->constrained('video_consulting_durations')->restrictOnDelete();
            $table->decimal('rate', 8, 2);
            $table->timestamps();

            $table->unique(['reader_id', 'duration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reader_video_consulting_rates');
    }
};
