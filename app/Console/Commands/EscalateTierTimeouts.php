<?php

// v1.0 — 2026-07-20 | Transfer unaccepted assignments out of a tier once they've sat past that
// tier's admin-configured timeout_hours, into its escalates_to_tier_id. Replaces the old lazy
// "tier-1 also visible to tier-2 after N hours" trick (Setting::getTier2ReleaseHours()) with a
// real per-tier, chainable transfer — see App\Models\Tier.

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\Tier;
use App\Services\ReaderNotificationService;
use Illuminate\Console\Command;

class EscalateTierTimeouts extends Command
{
    protected $signature   = 'tiers:escalate-timeouts';
    protected $description = 'Transfer unaccepted assignments to the next tier once their current tier\'s timeout has elapsed.';

    public function handle(ReaderNotificationService $notifier): int
    {
        $tiers = Tier::whereNotNull('timeout_hours')->whereNotNull('escalates_to_tier_id')->get();

        $totalEscalated = 0;

        foreach ($tiers as $tier) {
            $cutoff = now()->subHours($tier->timeout_hours);

            $assignments = Assignment::where('status', Assignment::STATUS_UNASSIGNED)
                ->whereNotNull('unassigned_at')
                ->where('unassigned_at', '<=', $cutoff)
                ->whereHas('tiers', fn ($q) => $q->where('tiers.id', $tier->id)
                    ->where('assignment_tier.created_at', '<=', $cutoff))
                ->get();

            foreach ($assignments as $assignment) {
                $assignment->tiers()->detach($tier->id);
                $assignment->tiers()->syncWithoutDetaching([$tier->escalates_to_tier_id]);

                $notifier->notifyNewAssignment($assignment->fresh());
                $totalEscalated++;
            }
        }

        if ($totalEscalated > 0) {
            $this->info("Escalated {$totalEscalated} assignment(s) to their next tier.");
        }

        return Command::SUCCESS;
    }
}
