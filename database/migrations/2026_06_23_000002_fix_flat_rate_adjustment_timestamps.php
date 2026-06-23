<?php

use App\Support\PayPeriod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $curStart = PayPeriod::current()[0];

        DB::table('editor_pay_adjustments')
            ->whereNotNull('editor_paid_at')
            ->where('created_at', '>=', $curStart->copy()->utc())
            ->where('description', 'like', 'Weekly flat rate%')
            ->update(['created_at' => $curStart->copy()->subMinute()->utc()]);
    }

    public function down(): void
    {
        // Data fix — no rollback needed
    }
};
