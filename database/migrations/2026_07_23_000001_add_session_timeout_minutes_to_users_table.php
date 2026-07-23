<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable per-user override of the global session_timeout_minutes setting.
            // Only admins may set this on themselves (see CheckSessionTimeout middleware).
            // Null = fall back to the global setting.
            $table->unsignedInteger('session_timeout_minutes')->nullable()->after('refresh_interval_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('session_timeout_minutes');
        });
    }
};
