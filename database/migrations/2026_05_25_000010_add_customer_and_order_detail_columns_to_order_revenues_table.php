<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_revenues', function (Blueprint $table) {
            $table->string('customer_name', 255)->nullable()->after('customer_email');
            $table->string('customer_phone', 50)->nullable()->after('customer_name');
            $table->text('customer_address')->nullable()->after('customer_phone');
            $table->string('script_title', 500)->nullable()->after('customer_address');
            $table->string('sku', 255)->nullable()->after('script_title');
            $table->string('ticket_summary', 500)->nullable()->after('sku');
            $table->unsignedSmallInteger('order_quantity')->nullable()->after('ticket_summary');
            $table->string('invoice_number', 100)->nullable()->after('order_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('order_revenues', function (Blueprint $table) {
            $table->dropColumn([
                'customer_name', 'customer_phone', 'customer_address',
                'script_title', 'sku', 'ticket_summary', 'order_quantity', 'invoice_number',
            ]);
        });
    }
};
