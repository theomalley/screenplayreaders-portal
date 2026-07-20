<?php

// 2026-07-20 | Data migration: seed Tier 0/1/2 rows (Tier 0 = onboarding, Tier 1 escalates to
// Tier 2 after the previous tier2_release_hours setting — same window, now a real transfer
// instead of a lazy visibility trick) and backfill reader_profile_tier / assignment_tier from
// the legacy boolean columns / assignments.tier before those columns are dropped.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $releaseHours = (int) (DB::table('settings')->where('key', 'tier2_release_hours')->value('value') ?? 24);

        $tier0Id = DB::table('tiers')->insertGetId([
            'name'          => 'Tier 0',
            'position'      => 0,
            'is_onboarding' => true,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
        $tier2Id = DB::table('tiers')->insertGetId([
            'name'       => 'Tier 2',
            'position'   => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $tier1Id = DB::table('tiers')->insertGetId([
            'name'                 => 'Tier 1',
            'position'             => 1,
            'timeout_hours'        => $releaseHours,
            'escalates_to_tier_id' => $tier2Id,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);

        $tierIdFor = [0 => $tier0Id, 1 => $tier1Id, 2 => $tier2Id];

        $readerRows = [];
        foreach (DB::table('reader_profiles')->select('id', 'tier_0', 'tier_1', 'tier_2')->get() as $profile) {
            foreach ([0, 1, 2] as $t) {
                if ($profile->{"tier_$t"}) {
                    $readerRows[] = [
                        'reader_profile_id' => $profile->id,
                        'tier_id'           => $tierIdFor[$t],
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];
                }
            }
        }
        foreach (array_chunk($readerRows, 500) as $chunk) {
            DB::table('reader_profile_tier')->insert($chunk);
        }

        $assignmentRows = [];
        foreach (DB::table('assignments')->select('id', 'tier', 'created_at')->get() as $assignment) {
            $t = (int) ($assignment->tier ?? 1);
            if (! isset($tierIdFor[$t])) {
                continue;
            }
            $assignmentRows[] = [
                'assignment_id' => $assignment->id,
                'tier_id'       => $tierIdFor[$t],
                'created_at'    => $assignment->created_at ?? $now,
                'updated_at'    => $now,
            ];
        }
        foreach (array_chunk($assignmentRows, 500) as $chunk) {
            DB::table('assignment_tier')->insert($chunk);
        }
    }

    public function down(): void
    {
        DB::table('assignment_tier')->truncate();
        DB::table('reader_profile_tier')->truncate();
        DB::table('tier_cross_visibility')->truncate();
        DB::table('tiers')->truncate();
    }
};
