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

/**
 * Video consulting requests are gated solely on email_notify_video_consulting (mandatory
 * once a reader has opted into offering calls — see ProfileController::updateNotifications()),
 * not the general email_notifications/email_notify_requests toggles a regular coverage
 * request uses.
 */
class ReaderNotificationServiceVideoConsultingGateTest extends TestCase
{
    use RefreshDatabase;

    private function makeReader(array $profileOverrides = []): User
    {
        $tier = Tier::create(['name' => 'Tier 1', 'position' => 1]);
        $user = User::factory()->create(['role' => 'reader']);
        $profile = $user->readerProfile()->create(array_merge([
            'initials'   => strtoupper(substr($user->name, 0, 2)),
            'first_name' => 'Test',
            'last_name'  => 'Reader',
        ], $profileOverrides));
        $profile->tiers()->sync([$tier->id]);

        return $user->fresh('readerProfile');
    }

    private function makeRequestedAssignment(User $reader, string $type): Assignment
    {
        $assignment = Assignment::create([
            'order_number'        => 'ORD-' . random_int(100000, 999999),
            'assignment_type'     => $type,
            'script_title'        => 'Test',
            'writer_name'         => 'Test Writer',
            'pay_rate'            => 50,
            'status'              => Assignment::STATUS_UNASSIGNED,
            'requested_reader_id' => $reader->id,
            'unassigned_at'       => now(),
        ]);
        $assignment->tiers()->sync($reader->readerProfile->tiers->pluck('id'));

        return $assignment->fresh('tiers');
    }

    public function test_video_consulting_notifies_even_when_the_general_email_toggle_is_off(): void
    {
        Mail::fake();

        $reader = $this->makeReader([
            'email_notifications'           => false,
            'email_notify_any'              => false,
            'email_notify_requests'         => false,
            'email_notify_video_consulting' => true,
        ]);

        $assignment = $this->makeRequestedAssignment($reader, Assignment::TYPE_VIDEO_CONSULTING);

        (new ReaderNotificationService())->notifyNewAssignment($assignment);

        Mail::assertQueued(NewAssignmentMail::class, fn ($m) => $m->hasTo($reader->email));
    }

    public function test_video_consulting_does_not_notify_when_that_specific_toggle_is_off(): void
    {
        Mail::fake();

        $reader = $this->makeReader([
            'email_notifications'           => true,
            'email_notify_any'              => true,
            'email_notify_requests'         => true,
            'email_notify_video_consulting' => false,
        ]);

        $assignment = $this->makeRequestedAssignment($reader, Assignment::TYPE_VIDEO_CONSULTING);

        (new ReaderNotificationService())->notifyNewAssignment($assignment);

        Mail::assertNotQueued(NewAssignmentMail::class);
    }

    public function test_a_regular_request_is_unaffected_by_the_video_consulting_toggle(): void
    {
        Mail::fake();

        $reader = $this->makeReader([
            'email_notifications'           => false,
            'email_notify_requests'         => false,
            'email_notify_video_consulting' => true,
        ]);

        $assignment = $this->makeRequestedAssignment($reader, 'script_coverage');

        (new ReaderNotificationService())->notifyNewAssignment($assignment);

        Mail::assertNotQueued(NewAssignmentMail::class);
    }
}
