<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('initials', 3);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('photo')->nullable();
            $table->string('paypal_email')->nullable();
            $table->enum('availability', ['available', 'unavailable'])->default('available');
            $table->string('availability_message', 500)->nullable();
            $table->string('upload_warning', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editor_profiles');
    }
};
