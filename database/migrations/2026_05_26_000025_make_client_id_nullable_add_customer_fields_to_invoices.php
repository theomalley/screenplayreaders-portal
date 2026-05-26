<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->change();
            $table->string('customer_name', 255)->nullable()->after('client_id');
            $table->string('customer_email', 255)->nullable()->after('customer_name');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'customer_email']);
            $table->foreignId('client_id')->nullable(false)->change();
        });
    }
};
