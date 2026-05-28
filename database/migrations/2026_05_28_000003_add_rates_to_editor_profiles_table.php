<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editor_profiles', function (Blueprint $table) {
            $table->decimal('editor_commission', 5, 2)->nullable()->after('timezone');
            $table->decimal('editor_weekly_flat', 8, 2)->nullable()->after('editor_commission');
        });
    }

    public function down(): void
    {
        Schema::table('editor_profiles', function (Blueprint $table) {
            $table->dropColumn(['editor_commission', 'editor_weekly_flat']);
        });
    }
};
