<?php

// v1.0 — 2026-08-08 | Admin-managed quality attestation checkboxes shown on the SR coverage
//                     form's Final Assessment step. Seeded with the previously-hardcoded
//                     quality_checked checkbox text so behavior is unchanged on deploy.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coverage_attestations', function (Blueprint $table) {
            $table->id();
            $table->string('text', 500);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('coverage_attestations')->insert([
            'text'       => "I've provided helpful, actionable feedback, have adhered to Screenplay Readers quality standards listed in the Reader Manual, and have reviewed my work for errors.",
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('coverage_attestations');
    }
};
