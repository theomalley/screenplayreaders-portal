<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->boolean('email_notifications')->default(false)->after('sms_notify_requests');
            $table->boolean('email_notify_any')->default(false)->after('email_notifications');
            $table->boolean('email_notify_rush')->default(false)->after('email_notify_any');
            $table->boolean('email_notify_requests')->default(false)->after('email_notify_rush');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn(['email_notifications', 'email_notify_any', 'email_notify_rush', 'email_notify_requests']);
        });
    }
};
