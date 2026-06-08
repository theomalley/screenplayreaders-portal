<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->unsignedInteger('check_interval_minutes')->default(1440); // daily
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamp('next_check_at')->nullable(); // null = check ASAP
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_sites');
    }
};
