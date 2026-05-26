<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_revenues', function (Blueprint $table) {
            // WooCommerce sends comma-separated quantities for multi-product orders (e.g. "1,1").
            // unsignedSmallInteger silently dropped these — change to string to store them faithfully.
            $table->string('order_quantity', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_revenues', function (Blueprint $table) {
            $table->unsignedSmallInteger('order_quantity')->nullable()->change();
        });
    }
};
