<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->text('bio_rejection_note')->nullable()->after('bio_pending');
            $table->text('photo_rejection_note')->nullable()->after('photo_pending');
        });

        Schema::table('editor_profiles', function (Blueprint $table) {
            $table->text('bio_rejection_note')->nullable()->after('bio_pending');
            $table->text('photo_rejection_note')->nullable()->after('photo_pending');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn(['bio_rejection_note', 'photo_rejection_note']);
        });

        Schema::table('editor_profiles', function (Blueprint $table) {
            $table->dropColumn(['bio_rejection_note', 'photo_rejection_note']);
        });
    }
};
