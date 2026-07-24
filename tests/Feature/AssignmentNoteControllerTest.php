<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentNoteControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssignment(): Assignment
    {
        return Assignment::create([
            'order_number' => 'TEST-' . random_int(100000, 999999),
            'script_title' => 'Test Script',
            'writer_name'  => 'Test Writer',
            'page_count'   => 100,
            'pay_rate'     => 50,
            'status'       => Assignment::STATUS_ASSIGNED,
        ]);
    }

    private function makeNote(Assignment $assignment, User $user): AssignmentNote
    {
        return AssignmentNote::create([
            'assignment_id' => $assignment->id,
            'user_id'       => $user->id,
            'body'          => 'Test note',
            'dismissed_by'  => [],
        ]);
    }

    public function test_reader_can_add_a_note(): void
    {
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment();

        $this->actingAs($reader)->post("/assignments/{$assignment->id}/notes", ['body' => 'Hi'])->assertRedirect();
    }

    public function test_admin_and_editor_cannot_add_a_note(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $editor     = User::factory()->create(['role' => 'editor']);
        $assignment = $this->makeAssignment();

        $this->actingAs($admin)->post("/assignments/{$assignment->id}/notes", ['body' => 'Hi'])->assertForbidden();
        $this->actingAs($editor)->post("/assignments/{$assignment->id}/notes", ['body' => 'Hi'])->assertForbidden();
    }

    public function test_admin_and_editor_can_reply_to_a_note(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $editor     = User::factory()->create(['role' => 'editor']);
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment();
        $note       = $this->makeNote($assignment, $reader);

        $this->actingAs($admin)->post("/assignment-notes/{$note->id}/reply", ['body' => 'Reply'])->assertRedirect();
        $this->actingAs($editor)->post("/assignment-notes/{$note->id}/reply", ['body' => 'Reply'])->assertRedirect();
    }

    public function test_reader_cannot_reply_to_a_note(): void
    {
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment();
        $note       = $this->makeNote($assignment, $reader);

        $this->actingAs($reader)->post("/assignment-notes/{$note->id}/reply", ['body' => 'Reply'])->assertForbidden();
    }

    public function test_any_authenticated_user_can_dismiss_a_note_or_reply(): void
    {
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment();
        $note       = $this->makeNote($assignment, $reader);

        $this->actingAs($reader)->post("/assignment-notes/{$note->id}/dismiss")->assertOk();
    }
}
