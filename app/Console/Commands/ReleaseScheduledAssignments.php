<?php

// v1.0 — 2026-06-02 | Release assignments to Available when their available_at time arrives

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Services\ReaderNotificationService;
use Illuminate\Console\Command;

class ReleaseScheduledAssignments extends Command
{
    protected $signature   = 'assignments:release-scheduled';
    protected $description = 'Set assignments to Available when their scheduled available_at time has passed.';

    public function handle(ReaderNotificationService $notifier): int
    {
        $due = Assignment::whereNotNull('available_at')
            ->where('available_at', '<=', now())
            ->where('status', '!=', Assignment::STATUS_UNASSIGNED)
            ->get();

        foreach ($due as $assignment) {
            $assignment->update([
                'status'             => Assignment::STATUS_UNASSIGNED,
                'unassigned_at'      => now(),
                'available_at'       => null,
                'assigned_reader_id' => null,
                'accepted_at'        => null,
                'reader_declined'    => false,
            ]);

            $notifier->notifyNewAssignment($assignment->fresh());
        }

        if ($due->count() > 0) {
            $this->info("Released {$due->count()} assignment(s) to Available.");
        }

        return Command::SUCCESS;
    }
}
