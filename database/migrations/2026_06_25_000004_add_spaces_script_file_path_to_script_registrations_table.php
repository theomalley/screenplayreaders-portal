<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('script_registrations', function (Blueprint $table) {
            $table->string('spaces_script_file_path')->nullable()->after('uploaded_file_name');
        });
    }

    public function down(): void
    {
        Schema::table('script_registrations', function (Blueprint $table) {
            $table->dropColumn('spaces_script_file_path');
        });
    }
};
