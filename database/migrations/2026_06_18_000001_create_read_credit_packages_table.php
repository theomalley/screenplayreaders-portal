<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('read_credit_packages', function (Blueprint $table) {
            $table->id();
            $table->string('customer_email');
            $table->string('customer_name');
            $table->string('woo_order_number', 64);
            $table->unsignedInteger('product_id');
            $table->unsignedSmallInteger('credits_purchased');
            $table->unsignedSmallInteger('credits_remaining');
            $table->uuid('upload_token')->unique();
            $table->string('status', 20)->default('active');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('customer_email');
            $table->index('woo_order_number');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('read_credit_packages');
    }
};
