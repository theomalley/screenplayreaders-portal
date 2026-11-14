<?php

// v1.0 — 2026-11-14 | Video consulting — "Favorite Genres" / "Favorite Films/Shows", up to 5
//                      each, shown on the Script Consulting page's reader rows. Reader
//                      self-serve editable, admin-overridable, no approval queue (unlike bio).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->json('favorite_genres')->nullable()->after('bio');
            $table->json('favorite_films')->nullable()->after('favorite_genres');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn(['favorite_genres', 'favorite_films']);
        });
    }
};
