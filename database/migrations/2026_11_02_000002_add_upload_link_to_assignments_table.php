<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('upload_link_url', 500)->nullable()->after('woo_discount_code');
            $table->timestamp('upload_link_generated_at')->nullable()->after('upload_link_url');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['upload_link_url', 'upload_link_generated_at']);
        });
    }
};
