<?php

use App\Models\OrderRevenue;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_revenues', function (Blueprint $table) {
            $table->foreignId('editor_id')->nullable()->after('woocommerce_order_id')
                ->constrained('users')->nullOnDelete();
        });

        // Backfill: every existing commission-eligible order was, until now, implicitly
        // credited to whichever editor recalculateCommission() happened to find first.
        // If exactly one real editor exists (true for every install prior to multi-editor
        // support), attribute all historical rows to them so nothing changes for existing data.
        $soleEditor = User::where('role', 'editor')->where('is_test', false)->first();
        if ($soleEditor && User::where('role', 'editor')->where('is_test', false)->count() === 1) {
            OrderRevenue::whereNull('editor_id')->update(['editor_id' => $soleEditor->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_revenues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('editor_id');
        });
    }
};
