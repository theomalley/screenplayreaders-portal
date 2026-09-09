<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FringeCalculator::applyWithCeiling() treats every fringe's `ceiling` as a wage-base
 * dollar figure: when labor exceeds it, tax = ceiling * rate. That's correct for every
 * other row (FUI/SUI wage bases, WGA/DGA/SAG pension-health caps). The seeded FICA row
 * stored 8853.60 -- which is 142,800 (the wage base someone correctly used) * 6.2%,
 * i.e. the already-computed max TAX amount, not a wage base. Feeding that through
 * `ceiling * rate` a second time silently produced FICA fringe capped at $548.92 for
 * any position, no matter how large its labor total -- an 82% understatement of the
 * real FICA fringe on very nearly every crew position, cast member, writer, and
 * producer whose labor total exceeds $8,853.60 (most full-time positions, within a
 * few weeks). Correcting the stored value to the wage base itself restores the
 * originally-intended $8,853.60 max tax figure via the same formula every other
 * fringe already uses correctly.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('budget_fringe_rates')
            ->where('slug', 'fica')
            ->update(['ceiling' => 142800.00]);
    }

    public function down(): void
    {
        DB::table('budget_fringe_rates')
            ->where('slug', 'fica')
            ->update(['ceiling' => 8853.60]);
    }
};
