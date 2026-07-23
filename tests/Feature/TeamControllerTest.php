<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_editor_can_view_team_page(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($admin)->get('/team')->assertOk();
        $this->actingAs($editor)->get('/team')->assertOk();
    }

    public function test_reader_cannot_view_team_page_by_default(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($reader)->get('/team')->assertForbidden();
    }

    public function test_admin_can_toggle_staff_visibility(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor', 'hidden_from_staff' => false]);

        $this->actingAs($admin)->post("/team/{$editor->id}/toggle-visibility")->assertRedirect();
        $this->assertTrue($editor->fresh()->hidden_from_staff);
    }

    public function test_editor_cannot_toggle_staff_visibility(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $other  = User::factory()->create(['role' => 'editor', 'hidden_from_staff' => false]);

        $this->actingAs($editor)->post("/team/{$other->id}/toggle-visibility")->assertForbidden();
    }
}
