<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('woo_discount_code', 32)->nullable()->after('helpscout_draft_dismissed_by');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('woo_discount_code');
        });
    }
};
