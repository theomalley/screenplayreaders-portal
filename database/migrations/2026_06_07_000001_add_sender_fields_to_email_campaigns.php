<?php

// v1.0 — 2026-06-07 | Add from_name, from_email, reply_to to email_campaigns

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->string('from_name', 100)->default('Screenplay Readers')->after('subject_line');
            $table->string('from_email', 255)->default('support@screenplayreaders.com')->after('from_name');
            $table->string('reply_to', 255)->default('support@screenplayreaders.com')->after('from_email');
        });
    }

    public function down(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->dropColumn(['from_name', 'from_email', 'reply_to']);
        });
    }
};
