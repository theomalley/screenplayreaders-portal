<?php

// v1.0 — 2026-11-27 | Theme's script-coverage form (woo_script-coverage.php) shows every
//                      reader as a card for Specific Reader Requests, greying out ones who are
//                      currently unavailable rather than hiding them. Some readers/admins would
//                      rather an unavailable reader not show up on that form at all — this
//                      per-reader opt-in toggle (defaulting to false, same as today's behavior)
//                      lets the admin hide a specific reader's card entirely whenever their
//                      availability is 'unavailable'.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->boolean('hide_when_unavailable')->default(false)->after('availability_message');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn('hide_when_unavailable');
        });
    }
};
