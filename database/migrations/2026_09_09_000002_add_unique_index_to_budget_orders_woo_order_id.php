<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BudgetWebhookController::store() checked idempotency with exists() then create(),
 * with no transaction or lock and no DB-level constraint backing it — two
 * near-simultaneous webhook deliveries for the same order (a retry, a duplicate
 * fire from woo_budgeting.php) could both pass exists() before either INSERT
 * landed, producing two BudgetOrder rows, two calculation/file-generation jobs,
 * and two customer emails with attached files. This unique index is the real,
 * race-safe guarantee; the controller now also catches the resulting constraint
 * violation. NULLs (legacy/manually-created rows with no woo_order_id) do not
 * conflict with each other under a unique index.
 *
 * NOTE: if this migration fails, it means duplicate non-null woo_order_id rows
 * already exist — investigate and reconcile those before retrying.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_orders', function (Blueprint $table) {
            $table->unique('woo_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('budget_orders', function (Blueprint $table) {
            $table->dropUnique(['woo_order_id']);
        });
    }
};
