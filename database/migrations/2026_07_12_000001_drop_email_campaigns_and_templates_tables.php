<?php

// Email Campaigns / Email Templates / Base Email Template feature removed —
// campaign creation and sending now happens directly in MailerLite.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('email_campaigns');
        Schema::dropIfExists('email_templates');
    }

    public function down(): void
    {
        // Feature removed — not recreating the old schema.
    }
};
