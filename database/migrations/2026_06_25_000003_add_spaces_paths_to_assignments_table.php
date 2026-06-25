<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('spaces_coverage_pdf_path')->nullable()->after('drive_coverage_pdf_id');
            $table->string('spaces_script_path')->nullable()->after('drive_script_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['spaces_coverage_pdf_path', 'spaces_script_path']);
        });
    }
};
