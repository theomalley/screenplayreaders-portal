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

class ReaderNotificationServiceDedupTest extends TestCase
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

    private function makeAssignment(string $orderNumber, array $tierIds, array $overrides = []): Assignment
    {
        $assignment = Assignment::create(array_merge([
            'order_number'  => $orderNumber,
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

    public function test_reader_eligible_for_both_sibling_slots_on_a_3reader_order_is_only_emailed_once(): void
    {
        Mail::fake();

        $tier1  = Tier::create(['name' => 'Tier 1', 'position' => 1]);
        $reader = $this->makeReader([$tier1->id]);

        $order = 'ORD-' . random_int(100000, 999999);
        $coverageSlot = $this->makeAssignment($order, [$tier1->id], ['assignment_type' => 'script_coverage']);
        $notesSlotA   = $this->makeAssignment($order, [$tier1->id], ['assignment_type' => 'notes_only']);
        $notesSlotB   = $this->makeAssignment($order, [$tier1->id], ['assignment_type' => 'notes_only']);

        $notifier = new ReaderNotificationService();
        $notifier->notifyNewAssignment($coverageSlot);
        $notifier->notifyNewAssignment($notesSlotA);
        $notifier->notifyNewAssignment($notesSlotB);

        Mail::assertQueued(NewAssignmentMail::class, 1);
    }

    public function test_escalation_renotify_does_not_reemail_a_reader_already_notified_for_the_order(): void
    {
        Mail::fake();

        $tier1  = Tier::create(['name' => 'Tier 1', 'position' => 1]);
        $tier2  = Tier::create(['name' => 'Tier 2', 'position' => 2]);
        $reader = $this->makeReader([$tier1->id, $tier2->id]);

        $order      = 'ORD-' . random_int(100000, 999999);
        $assignment = $this->makeAssignment($order, [$tier1->id]);

        $notifier = new ReaderNotificationService();
        $notifier->notifyNewAssignment($assignment);

        // Simulate EscalateTierTimeouts transferring the still-unassigned assignment
        // to a new tier and re-notifying — the reader was already emailed for this
        // order and must not be emailed again.
        $assignment->tiers()->syncWithoutDetaching([$tier2->id]);
        $notifier->notifyNewAssignment($assignment->fresh());

        Mail::assertQueued(NewAssignmentMail::class, 1);
    }

    public function test_readers_on_different_orders_are_each_still_notified(): void
    {
        Mail::fake();

        $tier1  = Tier::create(['name' => 'Tier 1', 'position' => 1]);
        $reader = $this->makeReader([$tier1->id]);

        $assignmentA = $this->makeAssignment('ORD-' . random_int(100000, 999999), [$tier1->id]);
        $assignmentB = $this->makeAssignment('ORD-' . random_int(100000, 999999), [$tier1->id]);

        $notifier = new ReaderNotificationService();
        $notifier->notifyNewAssignment($assignmentA);
        $notifier->notifyNewAssignment($assignmentB);

        Mail::assertQueued(NewAssignmentMail::class, 2);
    }
}
