<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpscout_order_conversations', function (Blueprint $table) {
            $table->timestamp('helpscout_sent_at')->nullable()->after('helpscout_conversation_id');
        });
    }

    public function down(): void
    {
        Schema::table('helpscout_order_conversations', function (Blueprint $table) {
            $table->dropColumn('helpscout_sent_at');
        });
    }
};
