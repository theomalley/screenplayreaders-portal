<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('read_credit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('read_credit_package_id')->constrained('read_credit_packages')->cascadeOnDelete();
            $table->string('event_type', 20);
            $table->unsignedSmallInteger('credits_before');
            $table->unsignedSmallInteger('credits_after');
            $table->text('note')->nullable();
            $table->string('script_title')->nullable();
            $table->string('order_number', 80)->nullable();
            $table->string('performed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('read_credit_package_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('read_credit_logs');
    }
};
