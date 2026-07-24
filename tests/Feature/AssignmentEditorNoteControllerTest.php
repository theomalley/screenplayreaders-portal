<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentEditorNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentEditorNoteControllerTest extends TestCase
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

    private function makeNote(Assignment $assignment, User $user): AssignmentEditorNote
    {
        return AssignmentEditorNote::create([
            'assignment_id' => $assignment->id,
            'user_id'       => $user->id,
            'body'          => 'Internal note',
        ]);
    }

    public function test_admin_and_editor_can_add_and_delete_an_editor_note(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $editor     = User::factory()->create(['role' => 'editor']);
        $assignment = $this->makeAssignment();

        $this->actingAs($admin)->post("/assignments/{$assignment->id}/editor-notes", ['body' => 'Note'])->assertRedirect();

        $note = $this->makeNote($assignment, $editor);
        $this->actingAs($editor)->delete("/assignment-editor-notes/{$note->id}")->assertRedirect();
        $this->assertDatabaseMissing('assignment_editor_notes', ['id' => $note->id]);
    }

    public function test_reader_cannot_add_or_delete_an_editor_note(): void
    {
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment();
        $note       = $this->makeNote($assignment, $reader);

        $this->actingAs($reader)->post("/assignments/{$assignment->id}/editor-notes", ['body' => 'Note'])->assertForbidden();
        $this->actingAs($reader)->delete("/assignment-editor-notes/{$note->id}")->assertForbidden();
    }
}
