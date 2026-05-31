<?php

// v1.0 — 2026-05-31 | One-time backfill: resolve short conversation numbers to large API IDs

namespace App\Console\Commands;

use App\Models\HelpScoutConversation;
use App\Services\HelpScoutService;
use Illuminate\Console\Command;

class ResolveHelpScoutIds extends Command
{
    protected $signature   = 'helpscout:resolve-ids {--dry-run : Preview without writing}';
    protected $description = 'Resolve short HelpScout conversation numbers to large API IDs';

    public function handle(HelpScoutService $hs): int
    {
        $rows = HelpScoutConversation::all()->filter(
            fn($c) => is_numeric($c->helpscout_conversation_id)
                   && (int) $c->helpscout_conversation_id < 10_000_000
        );

        if ($rows->isEmpty()) {
            $this->info('No rows need resolving.');
            return 0;
        }

        $dry = $this->option('dry-run');
        $this->info(($dry ? '[DRY RUN] ' : '') . "Resolving {$rows->count()} row(s)…");

        foreach ($rows as $row) {
            try {
                $resolved = $hs->findConversationIdByTicketNumber($row->helpscout_conversation_id);
                if ($resolved) {
                    $this->line("  {$row->order_number}: {$row->helpscout_conversation_id} → {$resolved}");
                    if (! $dry) {
                        $row->update(['helpscout_conversation_id' => $resolved]);
                    }
                } else {
                    $this->warn("  {$row->order_number}: {$row->helpscout_conversation_id} — not found in HelpScout");
                }
            } catch (\Throwable $e) {
                $this->error("  {$row->order_number}: {$row->helpscout_conversation_id} — error: {$e->getMessage()}");
            }
        }

        $this->info($dry ? 'Dry run complete — nothing written.' : 'Done.');
        return 0;
    }
}
