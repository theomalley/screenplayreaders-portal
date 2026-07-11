<?php

// 2026-07-11 | Seed the single shared tier-0 onboarding sandbox assignment via
// Assignment::ensureSandboxAssignment() — see that method for the field defaults.

use App\Models\Assignment;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Assignment::ensureSandboxAssignment();
    }

    public function down(): void
    {
        Assignment::where('order_number', 'SANDBOX-ONBOARDING')->delete();
    }
};
