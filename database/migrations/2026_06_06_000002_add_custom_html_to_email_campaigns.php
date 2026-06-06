<?php

// v1.0 — 2026-06-06 | Per-campaign custom HTML override for the email template editor

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->longText('custom_html')->nullable()->after('paragraph_bottom');
        });
    }

    public function down(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->dropColumn('custom_html');
        });
    }
};
