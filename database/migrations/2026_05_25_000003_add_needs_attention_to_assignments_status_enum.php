<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL-only syntax; sqlite's status column is a plain string (see
        // create_assignments_table) and already accepts any value.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE assignments MODIFY COLUMN status ENUM('incoming','unassigned','assigned','completed','qc','cancelled','on_hold_customer','on_hold_sr','needs_attention') NOT NULL DEFAULT 'incoming'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE assignments MODIFY COLUMN status ENUM('incoming','unassigned','assigned','completed','qc','cancelled','on_hold_customer','on_hold_sr') NOT NULL DEFAULT 'incoming'");
        }
    }
};
