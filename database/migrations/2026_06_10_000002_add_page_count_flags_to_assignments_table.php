<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->boolean('oversized_fee_included')->default(false)->after('page_count');
            $table->string('manual_page_flag')->nullable()->after('oversized_fee_included');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['oversized_fee_included', 'manual_page_flag']);
        });
    }
};
