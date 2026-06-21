<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_state_rates', function (Blueprint $table) {
            $table->id();
            $table->string('state_name', 50)->unique();
            $table->decimal('sui_rate', 10, 6);
            $table->decimal('sui_ceiling', 12, 2);
            $table->decimal('minimum_wage', 8, 2);
            $table->text('tax_incentive_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_state_rates');
    }
};
