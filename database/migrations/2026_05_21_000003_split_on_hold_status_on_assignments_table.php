<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The ENUM-widening ALTERs below are MySQL-only syntax. On other connections
        // (e.g. sqlite in tests) the status column is a plain string (see
        // create_assignments_table), so there's no enum to widen — just migrate the data.
        if (DB::getDriverName() === 'mysql') {
            // Step 1: expand enum so both old and new values coexist
            DB::statement("ALTER TABLE assignments MODIFY COLUMN status ENUM(
                'incoming','unassigned','assigned','completed','qc','cancelled','on_hold','on_hold_customer','on_hold_sr'
            ) NOT NULL DEFAULT 'incoming'");
        }

        // Step 2: migrate existing on_hold rows — treat as customer holds
        DB::statement("UPDATE assignments SET status = 'on_hold_customer' WHERE status = 'on_hold'");

        if (DB::getDriverName() === 'mysql') {
            // Step 3: remove the old value from the enum
            DB::statement("ALTER TABLE assignments MODIFY COLUMN status ENUM(
                'incoming','unassigned','assigned','completed','qc','cancelled','on_hold_customer','on_hold_sr'
            ) NOT NULL DEFAULT 'incoming'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE assignments MODIFY COLUMN status ENUM(
                'incoming','unassigned','assigned','completed','qc','cancelled','on_hold','on_hold_customer','on_hold_sr'
            ) NOT NULL DEFAULT 'incoming'");
        }

        DB::statement("UPDATE assignments SET status = 'on_hold' WHERE status IN ('on_hold_customer', 'on_hold_sr')");

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE assignments MODIFY COLUMN status ENUM(
                'incoming','unassigned','assigned','completed','qc','cancelled','on_hold'
            ) NOT NULL DEFAULT 'incoming'");
        }
    }
};
