<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\ReaderScriptNote;
use App\Models\ScriptHighlight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the ReaderScriptNotePolicy/ScriptHighlightPolicy fix
 * (2026-11-22): destroy() moved from an inline abort_unless() ownership check to
 * Laravel Policy auto-discovery — confirms the policies actually resolve and preserve
 * the same ownership behavior (403 for a non-owner, success for the owner).
 */
class ReaderScriptNoteAndHighlightDestroyPolicyTest extends TestCase
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

    public function test_a_reader_cannot_delete_another_readers_script_note(): void
    {
        $owner  = User::factory()->create(['role' => 'reader']);
        $other  = User::factory()->create(['role' => 'reader']);
        $note   = ReaderScriptNote::create([
            'assignment_id' => $this->makeAssignment()->id,
            'user_id'       => $owner->id,
            'body'          => 'My note',
        ]);

        $this->actingAs($other)
            ->deleteJson("/reading-notes/{$note->id}")
            ->assertForbidden();
    }

    public function test_a_reader_can_delete_their_own_script_note(): void
    {
        $owner = User::factory()->create(['role' => 'reader']);
        $note  = ReaderScriptNote::create([
            'assignment_id' => $this->makeAssignment()->id,
            'user_id'       => $owner->id,
            'body'          => 'My note',
        ]);

        $this->actingAs($owner)
            ->deleteJson("/reading-notes/{$note->id}")
            ->assertOk();

        $this->assertModelMissing($note);
    }

    public function test_a_reader_cannot_delete_another_readers_highlight(): void
    {
        $owner     = User::factory()->create(['role' => 'reader']);
        $other     = User::factory()->create(['role' => 'reader']);
        $highlight = ScriptHighlight::create([
            'assignment_id' => $this->makeAssignment()->id,
            'user_id'       => $owner->id,
            'page_number'   => 1,
            'rects'         => [['x' => 0, 'y' => 0, 'width' => 0.1, 'height' => 0.1]],
            'color'         => 'yellow',
        ]);

        $this->actingAs($other)
            ->deleteJson("/highlights/{$highlight->id}")
            ->assertForbidden();
    }

    public function test_a_reader_can_delete_their_own_highlight(): void
    {
        $owner     = User::factory()->create(['role' => 'reader']);
        $highlight = ScriptHighlight::create([
            'assignment_id' => $this->makeAssignment()->id,
            'user_id'       => $owner->id,
            'page_number'   => 1,
            'rects'         => [['x' => 0, 'y' => 0, 'width' => 0.1, 'height' => 0.1]],
            'color'         => 'yellow',
        ]);

        $this->actingAs($owner)
            ->deleteJson("/highlights/{$highlight->id}")
            ->assertOk();

        $this->assertModelMissing($highlight);
    }
}
