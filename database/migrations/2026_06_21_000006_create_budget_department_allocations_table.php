<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_department_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('department_slug', 50);
            $table->unsignedTinyInteger('budget_class');
            $table->decimal('percentage', 8, 6);
            $table->timestamps();

            $table->unique(['department_slug', 'budget_class']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_department_allocations');
    }
};
