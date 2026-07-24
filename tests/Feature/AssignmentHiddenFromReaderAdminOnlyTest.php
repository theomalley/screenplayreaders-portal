<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hide From Reader is an admin-only control — editors can manage every other
 * assignment field but must not be able to set/clear hidden_from_reader_ids,
 * either through the UI (checkbox panel hidden) or a crafted request (field
 * silently ignored server-side, existing value left untouched).
 */
class AssignmentHiddenFromReaderAdminOnlyTest extends TestCase
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
            'status'       => Assignment::STATUS_UNASSIGNED,
        ], $overrides));
    }

    private function updatePayload(Assignment $assignment, array $overrides = []): array
    {
        return array_merge([
            'order_number' => $assignment->order_number,
            'vendor'       => 'sr',
            'script_title' => $assignment->script_title,
            'writer_name'  => $assignment->writer_name,
            'page_count'   => $assignment->page_count,
            'status'       => Assignment::STATUS_UNASSIGNED,
        ], $overrides);
    }

    public function test_editor_cannot_hide_an_assignment_via_update_request(): void
    {
        $editor     = User::factory()->create(['role' => 'editor']);
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment();

        $this->actingAs($editor)
            ->patch("/assignments/{$assignment->id}", $this->updatePayload($assignment, [
                'hidden_from_reader_ids' => [$reader->id],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull($assignment->fresh()->hidden_from_reader_ids);
    }

    public function test_editor_update_does_not_clear_an_existing_hide_set_by_an_admin(): void
    {
        $editor     = User::factory()->create(['role' => 'editor']);
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment(['hidden_from_reader_ids' => [$reader->id]]);

        $this->actingAs($editor)
            ->patch("/assignments/{$assignment->id}", $this->updatePayload($assignment))
            ->assertSessionHasNoErrors();

        $this->assertEquals([$reader->id], $assignment->fresh()->hidden_from_reader_ids);
    }

    public function test_admin_can_hide_an_assignment_via_update_request(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment();

        $this->actingAs($admin)
            ->patch("/assignments/{$assignment->id}", $this->updatePayload($assignment, [
                'hidden_from_reader_ids' => [$reader->id],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertEquals([$reader->id], $assignment->fresh()->hidden_from_reader_ids);
    }

    public function test_edit_page_shows_hide_from_reader_panel_only_to_admins(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $editor     = User::factory()->create(['role' => 'editor']);
        $assignment = $this->makeAssignment();

        $this->actingAs($admin)
            ->get("/assignments/{$assignment->id}/edit")
            ->assertOk()
            ->assertSee('Hide From Reader');

        $this->actingAs($editor)
            ->get("/assignments/{$assignment->id}/edit")
            ->assertOk()
            ->assertDontSee('Hide From Reader');
    }
}
