<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('followup_questions', function (Blueprint $table) {
            $table->text('admin_note')->nullable()->after('edited_questions'); // private note from admin/editor to the reader
        });
    }

    public function down(): void
    {
        Schema::table('followup_questions', function (Blueprint $table) {
            $table->dropColumn('admin_note');
        });
    }
};
