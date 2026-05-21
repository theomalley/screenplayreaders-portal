<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE assignments MODIFY COLUMN status ENUM(
            'incoming','unassigned','assigned','completed','qc','cancelled','on_hold_customer','on_hold_sr'
        ) NOT NULL DEFAULT 'incoming'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE assignments MODIFY COLUMN status ENUM(
            'incoming','unassigned','assigned','completed','qc','cancelled','on_hold'
        ) NOT NULL DEFAULT 'incoming'");
    }
};
