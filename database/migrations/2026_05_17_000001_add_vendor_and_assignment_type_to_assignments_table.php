<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->enum('vendor', ['sr', 'wd'])->default('sr')->after('order_number');
            $table->string('assignment_type')->nullable()->after('vendor');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['vendor', 'assignment_type']);
        });
    }
};
