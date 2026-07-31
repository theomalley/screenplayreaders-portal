<?php

namespace Tests\Feature;

use App\Mail\NewAssignmentMail;
use App\Models\Assignment;
use App\Models\Tier;
use App\Models\User;
use App\Services\ReaderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReaderNotificationServiceTierGateTest extends TestCase
{
    use RefreshDatabase;

    private function makeReader(array $tierIds = [], array $profileOverrides = []): User
    {
        $user = User::factory()->create(['role' => 'reader']);
        $profile = $user->readerProfile()->create(array_merge([
            'initials'              => strtoupper(substr($user->name, 0, 2)),
            'first_name'            => 'Test',
            'last_name'             => 'Reader',
            'email_notifications'   => true,
            'email_notify_any'      => true,
            'email_notify_rush'     => true,
            'email_notify_requests' => true,
        ], $profileOverrides));

        if ($tierIds) {
            $profile->tiers()->sync($tierIds);
        }

        return $user->fresh('readerProfile');
    }

    private function makeAssignment(array $tierIds, array $overrides = []): Assignment
    {
        $assignment = Assignment::create(array_merge([
            'order_number'  => 'TEST-' . random_int(100000, 999999),
            'script_title'  => 'Test Script',
            'writer_name'   => 'Test Writer',
            'page_count'    => 100,
            'pay_rate'      => 50,
            'status'        => Assignment::STATUS_UNASSIGNED,
            'unassigned_at' => now(),
        ], $overrides));

        $assignment->tiers()->sync($tierIds);

        return $assignment->fresh('tiers');
    }

    public function test_general_pool_notification_skips_reader_outside_the_assignment_tier(): void
    {
        Mail::fake();

        $tier1 = Tier::create(['name' => 'Tier 1', 'position' => 1]);
        $tier2 = Tier::create(['name' => 'Tier 2', 'position' => 2]);

        $inTier    = $this->makeReader([$tier1->id]);
        $outOfTier = $this->makeReader([$tier2->id]);

        $assignment = $this->makeAssignment([$tier1->id]);

        (new ReaderNotificationService())->notifyNewAssignment($assignment);

        Mail::assertQueued(NewAssignmentMail::class, function ($mail) use ($inTier) {
            return $mail->hasTo($inTier->email);
        });
        Mail::assertNotQueued(NewAssignmentMail::class, function ($mail) use ($outOfTier) {
            return $mail->hasTo($outOfTier->email);
        });
    }

    public function test_general_pool_notification_skips_reader_the_assignment_is_hidden_from(): void
    {
        Mail::fake();

        $tier1 = Tier::create(['name' => 'Tier 1', 'position' => 1]);

        $visibleReader = $this->makeReader([$tier1->id]);
        $hiddenReader  = $this->makeReader([$tier1->id]);

        $assignment = $this->makeAssignment([$tier1->id], [
            'hidden_from_reader_ids' => [$hiddenReader->id],
        ]);

        (new ReaderNotificationService())->notifyNewAssignment($assignment);

        Mail::assertQueued(NewAssignmentMail::class, function ($mail) use ($visibleReader) {
            return $mail->hasTo($visibleReader->email);
        });
        Mail::assertNotQueued(NewAssignmentMail::class, function ($mail) use ($hiddenReader) {
            return $mail->hasTo($hiddenReader->email);
        });
    }

    public function test_requested_reader_notification_is_not_sent_when_assignment_is_hidden_from_them(): void
    {
        Mail::fake();

        $tier1 = Tier::create(['name' => 'Tier 1', 'position' => 1]);
        $requested = $this->makeReader([$tier1->id]);

        $assignment = $this->makeAssignment([$tier1->id], [
            'requested_reader_id'    => $requested->id,
            'hidden_from_reader_ids' => [$requested->id],
        ]);

        (new ReaderNotificationService())->notifyNewAssignment($assignment);

        Mail::assertNotQueued(NewAssignmentMail::class);
    }

    public function test_requested_reader_notification_still_sends_when_reader_is_eligible(): void
    {
        Mail::fake();

        $tier1 = Tier::create(['name' => 'Tier 1', 'position' => 1]);
        $requested = $this->makeReader([$tier1->id]);

        $assignment = $this->makeAssignment([$tier1->id], [
            'requested_reader_id' => $requested->id,
        ]);

        (new ReaderNotificationService())->notifyNewAssignment($assignment);

        Mail::assertQueued(NewAssignmentMail::class, function ($mail) use ($requested) {
            return $mail->hasTo($requested->email);
        });
    }
}
