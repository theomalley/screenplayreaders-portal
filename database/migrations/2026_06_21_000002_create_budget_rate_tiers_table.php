<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_rate_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crew_position_id')->constrained('budget_crew_positions')->cascadeOnDelete();
            $table->unsignedInteger('tier_code');
            $table->string('rate_type', 20);
            $table->decimal('rate_value', 12, 2)->default(0);
            $table->boolean('add_pub_fee')->default(false);
            $table->timestamps();

            $table->unique(['crew_position_id', 'tier_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_rate_tiers');
    }
};
