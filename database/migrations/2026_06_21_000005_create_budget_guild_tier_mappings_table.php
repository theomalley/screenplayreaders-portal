<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_guild_tier_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('guild', 20);
            $table->unsignedTinyInteger('budget_class');
            $table->unsignedInteger('tier_code');
            $table->timestamps();

            $table->unique(['guild', 'budget_class']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_guild_tier_mappings');
    }
};
