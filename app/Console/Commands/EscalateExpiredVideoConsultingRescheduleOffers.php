<?php

// v1.0 — 2026-11-09 | Video consulting Phase 3 — 48-hour reschedule-response deadline sweep,
//                      shaped like EscalateTierTimeouts. Hourly, not everyMinute() like the
//                      holds sweep — a 48-hour deadline doesn't need minute-level polling.

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\VideoConsultingRescheduleOffer;
use App\Services\VideoConsultingNotificationService;
use Illuminate\Console\Command;

class EscalateExpiredVideoConsultingRescheduleOffers extends Command
{
    protected $signature   = 'video-consulting:escalate-reschedule-deadlines';
    protected $description = 'Flag video-consulting bookings whose reschedule offer expired without a customer response.';

    public function handle(VideoConsultingNotificationService $notifier): int
    {
        $offers = VideoConsultingRescheduleOffer::where('status', VideoConsultingRescheduleOffer::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;

        foreach ($offers as $offer) {
            $offer->update(['status' => VideoConsultingRescheduleOffer::STATUS_EXPIRED]);

            $assignment = $offer->assignment;
            if (! $assignment || $assignment->status !== Assignment::STATUS_ASSIGNED) {
                continue;
            }

            $assignment->update([
                'status'                                  => Assignment::STATUS_NEEDS_ATTENTION,
                'video_consulting_needs_attention_reason'  => 'reschedule_deadline_expired',
                'needs_attention_notes'                    => 'Reschedule offer expired without a response from the customer.',
            ]);

            $notifier->notifyRescheduleDeadlineExpired($assignment->fresh());
            $count++;
        }

        if ($count > 0) {
            $this->info("Escalated {$count} expired reschedule offer(s).");
        }

        return Command::SUCCESS;
    }
}
