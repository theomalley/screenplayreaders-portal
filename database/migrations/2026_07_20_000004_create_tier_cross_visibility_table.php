<?php

// 2026-07-20 | Directional matrix controlling cross-tier access: readers in from_tier_id can
// see (can_view) and/or accept (can_accept) assignments belonging to to_tier_id. For rows
// where from_tier is the onboarding tier, can_accept is always stored false — the admin UI
// never exposes an accept toggle there, and App\Support\TierAccess/AssignmentPolicy also
// hard-enforce it regardless of what's stored.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tier_cross_visibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_tier_id')->constrained('tiers')->cascadeOnDelete();
            $table->foreignId('to_tier_id')->constrained('tiers')->cascadeOnDelete();
            $table->boolean('can_view')->default(false);
            $table->boolean('can_accept')->default(false);
            $table->timestamps();
            $table->unique(['from_tier_id', 'to_tier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tier_cross_visibility');
    }
};
