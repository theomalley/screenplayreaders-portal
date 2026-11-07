<?php

// v1.0 — 2026-11-07 | Video consulting (Whereby reader calls) Phase 1 — admin-creatable
//                      call-length options. base_rate/min_reader_rate/max_reader_rate are
//                      admin-set; the reader's own per-duration rate (reader_video_consulting_rates)
//                      must fall within [min_reader_rate, max_reader_rate]. Customer price =
//                      base_rate + the reader's rate. Soft-disable only (enabled flag) — never
//                      hard-deletable once a rate/hold/booking references it, see
//                      VideoConsultingDuration::canBeDeleted().

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_consulting_durations', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('minutes');
            $table->string('label');
            $table->decimal('base_rate', 8, 2)->default(0);
            $table->decimal('min_reader_rate', 8, 2)->default(0);
            $table->decimal('max_reader_rate', 8, 2)->default(0);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_consulting_durations');
    }
};
