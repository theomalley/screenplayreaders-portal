<?php

// 2026-07-20 | Pivot replacing the single assignments.tier column — an assignment can now
// belong to any number of tiers (or none). created_at is used by EscalateTierTimeouts as the
// "entered this tier at" timestamp.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_tier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tier_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['assignment_id', 'tier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_tier');
    }
};
