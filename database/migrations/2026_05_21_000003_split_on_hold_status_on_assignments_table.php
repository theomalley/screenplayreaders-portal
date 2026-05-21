<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: expand enum so both old and new values coexist
        DB::statement("ALTER TABLE assignments MODIFY COLUMN status ENUM(
            'incoming','unassigned','assigned','completed','qc','cancelled','on_hold','on_hold_customer','on_hold_sr'
        ) NOT NULL DEFAULT 'incoming'");

        // Step 2: migrate existing on_hold rows — treat as customer holds
        DB::statement("UPDATE assignments SET status = 'on_hold_customer' WHERE status = 'on_hold'");

        // Step 3: remove the old value from the enum
        DB::statement("ALTER TABLE assignments MODIFY COLUMN status ENUM(
            'incoming','unassigned','assigned','completed','qc','cancelled','on_hold_customer','on_hold_sr'
        ) NOT NULL DEFAULT 'incoming'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE assignments MODIFY COLUMN status ENUM(
            'incoming','unassigned','assigned','completed','qc','cancelled','on_hold','on_hold_customer','on_hold_sr'
        ) NOT NULL DEFAULT 'incoming'");

        DB::statement("UPDATE assignments SET status = 'on_hold' WHERE status IN ('on_hold_customer', 'on_hold_sr')");

        DB::statement("ALTER TABLE assignments MODIFY COLUMN status ENUM(
            'incoming','unassigned','assigned','completed','qc','cancelled','on_hold'
        ) NOT NULL DEFAULT 'incoming'");
    }
};
