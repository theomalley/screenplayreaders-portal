<?php

// 2026-07-20 | Pivot replacing reader_profiles.tier_0/tier_1/tier_2 — a reader can belong to
// any number of dynamic tiers.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reader_profile_tier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reader_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tier_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['reader_profile_id', 'tier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reader_profile_tier');
    }
};
