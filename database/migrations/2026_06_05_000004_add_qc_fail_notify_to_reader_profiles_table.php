<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->boolean('email_notify_qc_fail')->default(false)->after('sms_notify_followup');
            $table->boolean('sms_notify_qc_fail')->default(false)->after('email_notify_qc_fail');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn(['email_notify_qc_fail', 'sms_notify_qc_fail']);
        });
    }
};
