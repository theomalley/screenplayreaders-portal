<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->json('dismissed_by')->default('[]');
            $table->timestamps();
        });

        Schema::create('assignment_note_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->json('dismissed_by')->default('[]');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_note_replies');
        Schema::dropIfExists('assignment_notes');
    }
};
