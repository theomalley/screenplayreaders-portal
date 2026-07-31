<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editor_profiles', function (Blueprint $table) {
            $table->boolean('email_notifications')->default(false);
            $table->boolean('email_notify_any')->default(false);
            $table->boolean('email_notify_rush')->default(false);
            $table->boolean('email_notify_requests')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('editor_profiles', function (Blueprint $table) {
            $table->dropColumn(['email_notifications', 'email_notify_any', 'email_notify_rush', 'email_notify_requests']);
        });
    }
};
