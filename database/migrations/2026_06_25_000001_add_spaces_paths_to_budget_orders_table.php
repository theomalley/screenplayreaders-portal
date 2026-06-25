<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_orders', function (Blueprint $table) {
            $table->string('spaces_pdf_path')->nullable()->after('drive_pdf_id');
            $table->string('spaces_xlsx_path')->nullable()->after('drive_xlsx_id');
        });
    }

    public function down(): void
    {
        Schema::table('budget_orders', function (Blueprint $table) {
            $table->dropColumn(['spaces_pdf_path', 'spaces_xlsx_path']);
        });
    }
};
