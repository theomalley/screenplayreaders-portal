<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentTakeMeTest extends TestCase
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

    public function test_admin_can_turn_on_take_me(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $assignment = $this->makeAssignment();

        $this->actingAs($admin)
            ->postJson("/assignments/{$assignment->id}/take-me", [
                'enabled' => true,
                'style'   => 'rainbow',
                'text'    => 'Pick this one!',
            ])
            ->assertOk();

        $assignment->refresh();
        $this->assertTrue($assignment->take_me_enabled);
        $this->assertSame('rainbow', $assignment->take_me_style);
        $this->assertSame('Pick this one!', $assignment->take_me_text);
    }

    public function test_editor_can_turn_off_take_me(): void
    {
        $editor     = User::factory()->create(['role' => 'editor']);
        $assignment = $this->makeAssignment([
            'take_me_enabled' => true,
            'take_me_style'   => 'gold',
        ]);

        $this->actingAs($editor)
            ->postJson("/assignments/{$assignment->id}/take-me", ['enabled' => false])
            ->assertOk();

        $this->assertFalse($assignment->refresh()->take_me_enabled);
    }

    public function test_reader_cannot_toggle_take_me(): void
    {
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment();

        $this->actingAs($reader)
            ->postJson("/assignments/{$assignment->id}/take-me", ['enabled' => true, 'style' => 'gold'])
            ->assertForbidden();
    }

    public function test_invalid_style_is_rejected(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $assignment = $this->makeAssignment();

        $this->actingAs($admin)
            ->postJson("/assignments/{$assignment->id}/take-me", ['enabled' => true, 'style' => 'glitter'])
            ->assertStatus(422);
    }

    public function test_default_text_falls_back_per_style_when_blank(): void
    {
        $assignment = $this->makeAssignment([
            'take_me_enabled' => true,
            'take_me_style'   => 'neon',
        ]);

        $this->assertSame(Assignment::TAKE_ME_DEFAULT_TEXT['neon'], $assignment->takeMeDisplayText());
    }

    public function test_take_me_is_cleared_when_admin_assigns_a_reader(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment([
            'take_me_enabled' => true,
            'take_me_style'   => 'gold',
        ]);

        $this->actingAs($admin)
            ->patch("/assignments/{$assignment->id}/status", [
                'status'             => Assignment::STATUS_ASSIGNED,
                'assigned_reader_id' => $reader->id,
            ])
            ->assertRedirect();

        $this->assertFalse($assignment->refresh()->take_me_enabled);
    }
}
