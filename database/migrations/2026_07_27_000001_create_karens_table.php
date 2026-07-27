<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karens', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->date('flagged_date')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index(['first_name', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karens');
    }
};
