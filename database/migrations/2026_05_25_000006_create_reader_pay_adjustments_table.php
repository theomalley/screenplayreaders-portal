<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reader_pay_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');   // the reader being adjusted
            $table->decimal('amount', 10, 2);         // positive = bonus, negative = deduction
            $table->string('description');
            $table->unsignedBigInteger('added_by_user_id');
            $table->timestamp('reader_paid_at')->nullable(); // null = pending
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('added_by_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reader_pay_adjustments');
    }
};
