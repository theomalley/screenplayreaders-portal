<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_crew_positions', function (Blueprint $table) {
            $table->id();
            $table->string('line_item_id', 10)->unique();
            $table->string('slug', 50);
            $table->string('name', 100);
            $table->string('department', 50);
            $table->string('guild', 20);
            $table->json('phase_config')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_crew_positions');
    }
};
