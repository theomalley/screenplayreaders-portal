<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->string('availability')->default('available')->after('paypal_email'); // 'available' | 'unavailable'
            $table->text('availability_message')->nullable()->after('availability');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn(['availability', 'availability_message']);
        });
    }
};
