<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editor_profiles', function (Blueprint $table) {
            $table->string('about_photo')->nullable()->after('photo');
        });
    }

    public function down(): void
    {
        Schema::table('editor_profiles', function (Blueprint $table) {
            $table->dropColumn('about_photo');
        });
    }
};
