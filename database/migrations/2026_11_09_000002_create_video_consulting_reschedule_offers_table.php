<?php

// v1.0 — 2026-11-09 | Video consulting Phase 3 — reader proposes 5 alternate slots; the
//                      customer picks one via a magic link (token pattern mirrors
//                      FollowupToken). The original VideoConsultingHold/slot stays untouched
//                      until the customer actually responds.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_consulting_reschedule_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->json('candidate_slots');
            $table->dateTime('chosen_slot')->nullable();
            $table->string('status')->default('pending'); // pending | accepted | expired
            $table->dateTime('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_consulting_reschedule_offers');
    }
};
