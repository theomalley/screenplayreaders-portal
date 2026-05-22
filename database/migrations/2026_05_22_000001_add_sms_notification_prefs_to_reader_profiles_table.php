<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->boolean('sms_notify_any')->default(false)->after('sms_notifications');
            $table->boolean('sms_notify_rush')->default(false)->after('sms_notify_any');
            $table->boolean('sms_notify_requests')->default(false)->after('sms_notify_rush');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn(['sms_notify_any', 'sms_notify_rush', 'sms_notify_requests']);
        });
    }
};
