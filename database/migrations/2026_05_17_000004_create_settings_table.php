<?php

// v1.0 — 2026-05-17 | Portal settings: key/value store, seeded with reader pay rates

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('value', 255)->default('');
            $table->timestamps();
        });

        // Seed default reader pay rates (COGS from woo_order-financials.php / Zapier step-03)
        $defaults = [
            // SR base rates
            'rate_sr_script_coverage'   => '70.00',
            'rate_sr_notes_only'        => '55.00',
            'rate_sr_short'             => '55.00',
            'rate_sr_deep_dive'         => '215.00',
            'rate_sr_budget'            => '55.00',
            // SR modifiers
            'rate_sr_oversized_121_160' => '15.00',
            'rate_sr_rush'              => '50.00',
            'rate_sr_request'           => '40.00',
            'rate_sr_proofreading'      => '100.00',
            // WD base rates
            'rate_wd_coverage'          => '60.00',
            'rate_wd_development_notes' => '120.00',
            // WD modifiers
            'rate_wd_oversized_121_160' => '15.00',
            'rate_wd_request'           => '15.00',
        ];

        $now = now();
        foreach ($defaults as $key => $value) {
            DB::table('settings')->insert([
                'key'        => $key,
                'value'      => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
