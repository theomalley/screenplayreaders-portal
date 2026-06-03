<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->text('custom_message')->nullable()->after('bio');
        });
        Schema::table('editor_profiles', function (Blueprint $table) {
            $table->text('custom_message')->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn('custom_message');
        });
        Schema::table('editor_profiles', function (Blueprint $table) {
            $table->dropColumn('custom_message');
        });
    }
};
