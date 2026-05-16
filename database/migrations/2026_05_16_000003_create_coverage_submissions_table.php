<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Stub — full field spec TBD. See CLAUDE.md "Outstanding Decisions".
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coverage_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coverage_submissions');
    }
};
