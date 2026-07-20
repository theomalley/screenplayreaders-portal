<?php

// 2026-07-20 | Dynamic reader tiers — replaces the hardcoded tier_0/tier_1/tier_2 concept.
// is_onboarding marks the single sandbox-only tier (formerly "tier 0"); timeout_hours +
// escalates_to_tier_id drive auto-escalation (App\Console\Commands\EscalateTierTimeouts);
// allowed_assignment_types is a nullable JSON allowlist (null = no restriction).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_onboarding')->default(false);
            $table->unsignedInteger('timeout_hours')->nullable();
            $table->foreignId('escalates_to_tier_id')->nullable()->constrained('tiers')->nullOnDelete();
            $table->json('allowed_assignment_types')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiers');
    }
};
