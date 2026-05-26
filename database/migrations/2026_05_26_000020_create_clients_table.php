<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->text('sr_address')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('country', 100)->nullable()->default('USA');
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('last_invoice_number')->default(0);
            $table->enum('invoice_type', ['pdf', 'stripe'])->default('stripe');
            $table->string('stripe_customer_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
