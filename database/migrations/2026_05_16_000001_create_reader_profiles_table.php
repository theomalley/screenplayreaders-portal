<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reader_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('initials', 10);
            $table->string('first_name');
            $table->string('last_name');
            $table->string('photo')->nullable();
            $table->unsignedTinyInteger('max_concurrent_assignments')->default(3);
            $table->string('paypal_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reader_profiles');
    }
};
