<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('script_registrations', function (Blueprint $table) {
            $table->string('spaces_certificate_pdf_path')->nullable()->after('drive_certificate_pdf_id');
        });
    }

    public function down(): void
    {
        Schema::table('script_registrations', function (Blueprint $table) {
            $table->dropColumn('spaces_certificate_pdf_path');
        });
    }
};
