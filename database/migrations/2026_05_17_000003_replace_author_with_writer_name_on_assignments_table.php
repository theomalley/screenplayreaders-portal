<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('writer_name')->after('script_title');
            $table->dropColumn(['author_first_initial', 'author_last_name']);
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->char('author_first_initial', 1)->after('script_title');
            $table->string('author_last_name', 100)->after('author_first_initial');
            $table->dropColumn('writer_name');
        });
    }
};
