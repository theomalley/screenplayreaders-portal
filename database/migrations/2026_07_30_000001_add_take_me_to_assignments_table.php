<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->boolean('take_me_enabled')->default(false)->after('karen_alert_note');
            $table->string('take_me_style')->nullable()->after('take_me_enabled');
            $table->string('take_me_text')->nullable()->after('take_me_style');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['take_me_enabled', 'take_me_style', 'take_me_text']);
        });
    }
};
