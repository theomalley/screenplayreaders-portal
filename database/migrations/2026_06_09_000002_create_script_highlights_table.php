<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('script_highlights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->text('text')->nullable();
            $table->json('rects');
            $table->string('color', 20)->default('yellow');
            $table->timestamps();

            $table->index(['assignment_id', 'user_id', 'page_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('script_highlights');
    }
};
