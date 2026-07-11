<?php

// v1.0 — 2026-07-11 | Admin-managed additional rate items (Rates page, free-form add/edit/delete)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_items', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->decimal('amount', 10, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_items');
    }
};
