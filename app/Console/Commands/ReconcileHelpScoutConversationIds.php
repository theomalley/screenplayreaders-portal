<?php

// v1.0 — 2026-07-16 | Scheduled reconciliation: HelpScout 200s a GET for a conversation ID
//                     that has since been merged into another one, but the body's "id" field
//                     reflects the survivor and write endpoints 404 on the old ID. This scans
//                     all stored IDs and auto-heals any that have drifted, before a draft
//                     attempt hits the stale ID and fails.

namespace App\Console\Commands;

use App\Models\HelpScoutConversation;
use App\Services\HelpScoutService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileHelpScoutConversationIds extends Command
{
    protected $signature   = 'helpscout:reconcile-conversation-ids {--dry-run : Preview without writing}';
    protected $description = 'Detect HelpScout conversation IDs merged into a new ID and auto-heal stored records';

    public function handle(HelpScoutService $hs): int
    {
        $dry  = $this->option('dry-run');
        $rows = HelpScoutConversation::all();

        $this->info(($dry ? '[DRY RUN] ' : '') . "Checking {$rows->count()} stored conversation ID(s)…");

        $merged  = 0;
        $missing = 0;

        foreach ($rows as $row) {
            $storedId = $row->helpscout_conversation_id;

            try {
                $resolved = $hs->resolveConversationId($storedId);
            } catch (\Throwable $e) {
                $this->error("  {$row->order_number}: {$storedId} — error checking: {$e->getMessage()}");
                continue;
            }

            if ($resolved === null) {
                $missing++;
                $this->warn("  {$row->order_number}: {$storedId} — no longer resolves (deleted). Leaving as-is; buildDraft() falls back to a subject-line search on next attempt.");
                Log::warning('HelpScout reconcile: stored conversation ID missing', [
                    'order_number' => $row->order_number,
                    'stored_id'    => $storedId,
                ]);

                usleep(150_000);
                continue;
            }

            if ($resolved !== $storedId) {
                $merged++;
                $this->line("  {$row->order_number}: {$storedId} \xE2\x86\x92 {$resolved} (merged)");
                Log::info('HelpScout reconcile: auto-healed merged conversation ID', [
                    'order_number' => $row->order_number,
                    'old_id'       => $storedId,
                    'new_id'       => $resolved,
                ]);

                if (! $dry) {
                    $row->update(['helpscout_conversation_id' => $resolved]);
                }
            }

            usleep(150_000);
        }

        $this->info(($dry ? '[DRY RUN] ' : '') . "Done. {$merged} merged ID(s) healed, {$missing} missing.");

        return 0;
    }
}
