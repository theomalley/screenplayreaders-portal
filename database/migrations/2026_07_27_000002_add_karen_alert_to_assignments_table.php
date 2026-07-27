<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->boolean('karen_alert')->default(false)->after('client_id');
            $table->string('karen_alert_note')->nullable()->after('karen_alert');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['karen_alert', 'karen_alert_note']);
        });
    }
};
