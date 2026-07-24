<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the 3 AssignmentController actions that still used inline
 * abort_unless(isAdminOrEditor()) rather than AssignmentPolicy: streamCoverage,
 * dismissHelpscoutDraft, and the shared pageCountFlagDraft() helper behind
 * over120()/over160(). Asserts the authorization boundary — streamCoverage's
 * "not 403" case deliberately doesn't set drive_coverage_pdf_id, so it 404s
 * past the auth check rather than reaching GoogleDriveService/network calls.
 */
class AssignmentControllerExtraActionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssignment(array $overrides = []): Assignment
    {
        return Assignment::create(array_merge([
            'order_number' => 'TEST-' . random_int(100000, 999999),
            'script_title' => 'Test Script',
            'writer_name'  => 'Test Writer',
            'page_count'   => 100,
            'pay_rate'     => 50,
            'status'       => Assignment::STATUS_INCOMING,
        ], $overrides));
    }

    public function test_admin_and_editor_are_not_forbidden_from_stream_coverage(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $editor     = User::factory()->create(['role' => 'editor']);
        $assignment = $this->makeAssignment();

        $this->assertNotEquals(403, $this->actingAs($admin)->get("/assignments/{$assignment->id}/coverage-pdf")->getStatusCode());
        $this->assertNotEquals(403, $this->actingAs($editor)->get("/assignments/{$assignment->id}/coverage-pdf")->getStatusCode());
    }

    public function test_reader_is_forbidden_from_stream_coverage(): void
    {
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment();

        $this->actingAs($reader)->get("/assignments/{$assignment->id}/coverage-pdf")->assertForbidden();
    }

    public function test_admin_and_editor_can_dismiss_helpscout_draft(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $editor     = User::factory()->create(['role' => 'editor']);
        $assignment = $this->makeAssignment();

        $this->assertNotEquals(403, $this->actingAs($admin)->post("/assignments/{$assignment->id}/dismiss-helpscout-draft")->getStatusCode());
        $this->assertNotEquals(403, $this->actingAs($editor)->post("/assignments/{$assignment->id}/dismiss-helpscout-draft")->getStatusCode());
    }

    public function test_reader_cannot_dismiss_helpscout_draft(): void
    {
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment();

        $this->actingAs($reader)->post("/assignments/{$assignment->id}/dismiss-helpscout-draft")->assertForbidden();
    }

    public function test_admin_and_editor_are_not_forbidden_from_over120_and_over160(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $editor     = User::factory()->create(['role' => 'editor']);
        $assignment = $this->makeAssignment();

        $this->assertNotEquals(403, $this->actingAs($admin)->post("/assignments/{$assignment->id}/over-120")->getStatusCode());
        $this->assertNotEquals(403, $this->actingAs($editor)->post("/assignments/{$assignment->id}/over-160")->getStatusCode());
    }

    public function test_reader_cannot_use_over120_or_over160(): void
    {
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment();

        $this->actingAs($reader)->post("/assignments/{$assignment->id}/over-120")->assertForbidden();
        $this->actingAs($reader)->post("/assignments/{$assignment->id}/over-160")->assertForbidden();
    }
}
