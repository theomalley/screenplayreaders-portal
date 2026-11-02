<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->decimal('rate_script_coverage', 8, 2)->nullable()->after('max_concurrent_assignments');
            $table->decimal('rate_notes_only', 8, 2)->nullable()->after('rate_script_coverage');
            $table->decimal('rate_deep_dive', 8, 2)->nullable()->after('rate_notes_only');
            $table->decimal('rate_proofreading', 8, 2)->nullable()->after('rate_deep_dive');
            $table->decimal('rate_oversized_121_160', 8, 2)->nullable()->after('rate_proofreading');
            $table->decimal('rate_rush', 8, 2)->nullable()->after('rate_oversized_121_160');
            $table->decimal('rate_request', 8, 2)->nullable()->after('rate_rush');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'rate_script_coverage',
                'rate_notes_only',
                'rate_deep_dive',
                'rate_proofreading',
                'rate_oversized_121_160',
                'rate_rush',
                'rate_request',
            ]);
        });
    }
};
