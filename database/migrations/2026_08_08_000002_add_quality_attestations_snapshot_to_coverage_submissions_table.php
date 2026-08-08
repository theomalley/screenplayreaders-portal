<?php

// v1.0 — 2026-08-08 | Snapshot the exact attestation text a reader confirmed at submit time,
//                     so later edits to coverage_attestations don't rewrite submission history.
//                     quality_checked is kept as-is for backward compatibility with existing
//                     reports/exports — it's still set true on every SR submission.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coverage_submissions', function (Blueprint $table) {
            $table->json('quality_attestations_snapshot')->nullable()->after('quality_checked');
        });
    }

    public function down(): void
    {
        Schema::table('coverage_submissions', function (Blueprint $table) {
            $table->dropColumn('quality_attestations_snapshot');
        });
    }
};
