<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_revenues', function (Blueprint $table) {
            $table->index('ordered_at');
            $table->index('customer_email');
        });
    }

    public function down(): void
    {
        Schema::table('order_revenues', function (Blueprint $table) {
            $table->dropIndex(['ordered_at']);
            $table->dropIndex(['customer_email']);
        });
    }
};
