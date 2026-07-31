<?php

namespace Tests\Feature;

use App\Mail\NewAssignmentMail;
use App\Models\Assignment;
use App\Models\User;
use App\Services\ReaderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Admin-only "preview the new-assignment email" toggle — lets an admin see exactly what a
 * reader would receive, for testing deliverability and dialing in the MailerSend template.
 * Editors must never get this (scope was explicitly admin-only, not admin+editor).
 */
class AdminNotificationPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $profileOverrides = []): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->editorProfile()->create(array_merge([
            'initials'   => strtoupper(substr($user->name, 0, 2)),
            'first_name' => 'Test',
            'last_name'  => 'Admin',
        ], $profileOverrides));

        return $user->fresh('editorProfile');
    }

    private function makeEditor(array $profileOverrides = []): User
    {
        $user = User::factory()->create(['role' => 'editor']);
        $user->editorProfile()->create(array_merge([
            'initials'   => strtoupper(substr($user->name, 0, 2)),
            'first_name' => 'Test',
            'last_name'  => 'Editor',
        ], $profileOverrides));

        return $user->fresh('editorProfile');
    }

    private function makeAssignment(array $overrides = []): Assignment
    {
        return Assignment::create(array_merge([
            'order_number'  => 'TEST-' . random_int(100000, 999999),
            'script_title'  => 'Test Script',
            'writer_name'   => 'Test Writer',
            'page_count'    => 100,
            'pay_rate'      => 50,
            'status'        => Assignment::STATUS_UNASSIGNED,
            'unassigned_at' => now(),
        ], $overrides));
    }

    public function test_opted_in_admin_receives_a_copy_of_the_general_pool_email(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin([
            'email_notifications' => true,
            'email_notify_any'    => true,
        ]);

        $assignment = $this->makeAssignment();

        (new ReaderNotificationService())->notifyNewAssignment($assignment);

        Mail::assertQueued(NewAssignmentMail::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });
    }

    public function test_admin_without_email_notifications_enabled_is_not_notified(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin([
            'email_notifications' => false,
            'email_notify_any'    => true,
        ]);

        $assignment = $this->makeAssignment();

        (new ReaderNotificationService())->notifyNewAssignment($assignment);

        Mail::assertNotQueued(NewAssignmentMail::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });
    }

    public function test_editor_with_the_same_toggles_set_is_never_notified(): void
    {
        Mail::fake();

        $editor = $this->makeEditor([
            'email_notifications'   => true,
            'email_notify_any'      => true,
            'email_notify_rush'     => true,
            'email_notify_requests' => true,
        ]);

        $assignment = $this->makeAssignment(['rush' => true]);

        (new ReaderNotificationService())->notifyNewAssignment($assignment);

        Mail::assertNotQueued(NewAssignmentMail::class, function ($mail) use ($editor) {
            return $mail->hasTo($editor->email);
        });
    }

    public function test_admin_opted_into_rush_only_is_skipped_for_non_rush_assignment(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin([
            'email_notifications' => true,
            'email_notify_any'    => false,
            'email_notify_rush'   => true,
        ]);

        $assignment = $this->makeAssignment(['rush' => false]);

        (new ReaderNotificationService())->notifyNewAssignment($assignment);

        Mail::assertNotQueued(NewAssignmentMail::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });
    }

    public function test_admin_opted_into_rush_only_is_notified_for_rush_assignment(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin([
            'email_notifications' => true,
            'email_notify_any'    => false,
            'email_notify_rush'   => true,
        ]);

        $assignment = $this->makeAssignment(['rush' => true]);

        (new ReaderNotificationService())->notifyNewAssignment($assignment);

        Mail::assertQueued(NewAssignmentMail::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });
    }

    public function test_admin_opted_into_requests_is_notified_for_a_requested_assignment_regardless_of_eligibility(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin([
            'email_notifications'   => true,
            'email_notify_requests' => true,
        ]);

        // requested reader has no readerProfile / notification prefs set, so they
        // wouldn't pass accept() eligibility checks either — the admin preview must
        // not depend on the accept() policy at all.
        $assignment = $this->makeAssignment(['requested_reader_id' => User::factory()->create(['role' => 'reader'])->id]);

        (new ReaderNotificationService())->notifyNewAssignment($assignment);

        Mail::assertQueued(NewAssignmentMail::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });
    }

    public function test_admin_not_opted_into_requests_is_not_notified_for_a_requested_assignment(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin([
            'email_notifications'   => true,
            'email_notify_any'      => true,
            'email_notify_requests' => false,
        ]);

        $assignment = $this->makeAssignment(['requested_reader_id' => User::factory()->create(['role' => 'reader'])->id]);

        (new ReaderNotificationService())->notifyNewAssignment($assignment);

        Mail::assertNotQueued(NewAssignmentMail::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });
    }

    public function test_admin_can_save_notification_preferences_via_the_profile_route(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->patch('/profile/notifications', [
            'email_notifications'   => '1',
            'email_notify_any'      => '1',
            'email_notify_rush'     => '1',
            'email_notify_requests' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('editor_profiles', [
            'user_id'                => $admin->id,
            'email_notifications'    => true,
            'email_notify_any'       => true,
            'email_notify_rush'      => true,
            'email_notify_requests'  => true,
        ]);
    }

    public function test_editor_is_forbidden_from_the_notifications_route(): void
    {
        $editor = $this->makeEditor();

        $this->actingAs($editor)->patch('/profile/notifications', [
            'email_notifications' => '1',
        ])->assertForbidden();
    }

    public function test_admin_profile_edit_page_shows_the_notification_preview_form(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get('/profile')
            ->assertOk()
            ->assertSee('Notification Preview');
    }

    public function test_editor_profile_edit_page_does_not_show_the_notification_preview_form(): void
    {
        $editor = $this->makeEditor();

        $this->actingAs($editor)->get('/profile')
            ->assertOk()
            ->assertDontSee('Notification Preview');
    }
}
