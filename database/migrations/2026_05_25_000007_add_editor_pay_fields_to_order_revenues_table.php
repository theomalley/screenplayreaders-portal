<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_revenues', function (Blueprint $table) {
            $table->timestamp('editor_paid_at')->nullable()->after('woocommerce_order_id');
            $table->text('line_items_json')->nullable()->after('services_purchased');
        });
    }

    public function down(): void
    {
        Schema::table('order_revenues', function (Blueprint $table) {
            $table->dropColumn(['editor_paid_at', 'line_items_json']);
        });
    }
};
