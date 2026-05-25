<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_revenues', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->unsignedBigInteger('woocommerce_order_id')->nullable();
            $table->timestamp('ordered_at');
            $table->decimal('order_total', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('cog_reader', 10, 2)->default(0);
            $table->decimal('cog_processing', 10, 2)->default(0);
            $table->decimal('cog_precommission', 10, 2)->default(0);
            $table->decimal('cog_commission', 10, 2)->default(0);
            $table->decimal('cog_total', 10, 2)->default(0);
            $table->decimal('net_revenue', 10, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('coupon_code')->nullable();
            $table->string('customer_email')->nullable();
            $table->text('services_purchased')->nullable();
            $table->string('staff_member')->nullable();
            $table->boolean('skip_commission')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_revenues');
    }
};
