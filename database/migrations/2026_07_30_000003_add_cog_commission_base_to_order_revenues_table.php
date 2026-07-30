<?php

use App\Models\OrderRevenue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_revenues', function (Blueprint $table) {
            $table->decimal('cog_commission_base', 10, 2)->nullable()->after('cog_commission');
        });

        // Backfill: pre-existing rows have no notion of a QC penalty yet, so their current
        // commission IS the base — otherwise applyQcAdjustmentForOrder() would have nothing
        // to derive from and would treat every historical order as having zero commission.
        OrderRevenue::whereNull('cog_commission_base')->update([
            'cog_commission_base' => DB::raw('cog_commission'),
        ]);
    }

    public function down(): void
    {
        Schema::table('order_revenues', function (Blueprint $table) {
            $table->dropColumn('cog_commission_base');
        });
    }
};
