<?php

// v1.0 — 2026-11-11 | Video consulting Phase 7 (partial) — 24h pre-call reminder sweep for both
//                      customer and reader. Runs every 30 minutes against a ±20-minute window
//                      around "now + 24h" (40-minute width comfortably overlaps a 30-minute
//                      cadence, so no assignment can fall through the gap between runs).
//                      Idempotent via video_consulting_reminder_24h_sent_at, checked at send time
//                      (not query time) to stay safe against an overlapping run.

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Services\VideoConsultingNotificationService;
use Illuminate\Console\Command;

class SendVideoConsultingCallReminders extends Command
{
    protected $signature   = 'video-consulting:send-call-reminders';
    protected $description = 'Email the customer and reader a reminder 24 hours before an upcoming video-consulting call.';

    public function handle(VideoConsultingNotificationService $notifier): int
    {
        $target = now()->addHours(24);
        $rangeStart = $target->copy()->subMinutes(20);
        $rangeEnd = $target->copy()->addMinutes(20);

        $assignments = Assignment::query()
            ->where('assignment_type', Assignment::TYPE_VIDEO_CONSULTING)
            ->where('status', Assignment::STATUS_ASSIGNED)
            ->whereNotNull('video_consulting_call_token')
            ->whereNull('video_consulting_reminder_24h_sent_at')
            ->whereHas('videoConsultingHold', function ($query) use ($rangeStart, $rangeEnd) {
                $query->whereBetween('slot_start', [$rangeStart, $rangeEnd]);
            })
            ->get();

        $sent = 0;

        foreach ($assignments as $assignment) {
            // Re-check at send time, not just at query time — guards against a race where
            // another overlapping run already sent this reminder a moment ago.
            if ($assignment->fresh()->video_consulting_reminder_24h_sent_at) {
                continue;
            }

            $notifier->notifyCallReminder($assignment);
            $assignment->update(['video_consulting_reminder_24h_sent_at' => now()]);
            $sent++;
        }

        if ($sent > 0) {
            $this->info("Sent {$sent} video-consulting call reminder(s).");
        }

        return Command::SUCCESS;
    }
}
