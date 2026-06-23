<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_sites', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('active');
            $table->string('contact_email')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('partner_sites', function (Blueprint $table) {
            $table->dropColumn(['source', 'contact_email']);
        });
    }
};
