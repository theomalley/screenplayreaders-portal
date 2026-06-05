<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->string('about_photo')->nullable()->after('photo_rejection_note');
            $table->string('about_photo_pending')->nullable()->after('about_photo');
            $table->text('about_photo_rejection_note')->nullable()->after('about_photo_pending');
        });

        Schema::table('editor_profiles', function (Blueprint $table) {
            $table->string('about_photo_pending')->nullable()->after('about_photo');
            $table->text('about_photo_rejection_note')->nullable()->after('about_photo_pending');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn(['about_photo', 'about_photo_pending', 'about_photo_rejection_note']);
        });

        Schema::table('editor_profiles', function (Blueprint $table) {
            $table->dropColumn(['about_photo_pending', 'about_photo_rejection_note']);
        });
    }
};
