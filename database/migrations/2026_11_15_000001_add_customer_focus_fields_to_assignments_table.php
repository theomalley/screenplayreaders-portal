<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->json('customer_focus_categories')->nullable()->after('page_count');
            $table->text('customer_focus_notes')->nullable()->after('customer_focus_categories');
            $table->text('customer_focus_exclude_notes')->nullable()->after('customer_focus_notes');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['customer_focus_categories', 'customer_focus_notes', 'customer_focus_exclude_notes']);
        });
    }
};
