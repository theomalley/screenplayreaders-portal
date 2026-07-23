<?php

namespace Tests\Feature;

use App\Models\Tier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TierControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_manage_tiers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tier  = Tier::create(['name' => 'Tier 1', 'position' => 1]);

        $this->actingAs($admin)->get('/settings/tiers')->assertOk();

        $this->actingAs($admin)
            ->post('/settings/tiers', ['name' => 'Tier 2'])
            ->assertRedirect();
        $this->assertDatabaseHas('tiers', ['name' => 'Tier 2']);

        $this->actingAs($admin)
            ->patch("/settings/tiers/{$tier->id}", ['name' => 'Tier 1 Renamed', 'position' => 1])
            ->assertRedirect();
        $this->assertDatabaseHas('tiers', ['id' => $tier->id, 'name' => 'Tier 1 Renamed']);

        $this->actingAs($admin)->patch('/settings/tiers-visibility', [])->assertRedirect();

        $this->actingAs($admin)->delete("/settings/tiers/{$tier->id}")->assertRedirect();
        $this->assertDatabaseMissing('tiers', ['id' => $tier->id]);
    }

    public function test_editor_cannot_view_or_manage_tiers(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $tier   = Tier::create(['name' => 'Tier 1', 'position' => 1]);

        $this->actingAs($editor)->get('/settings/tiers')->assertForbidden();
        $this->actingAs($editor)->post('/settings/tiers', ['name' => 'Tier 2'])->assertForbidden();
        $this->actingAs($editor)->patch("/settings/tiers/{$tier->id}", ['name' => 'x', 'position' => 1])->assertForbidden();
        $this->actingAs($editor)->delete("/settings/tiers/{$tier->id}")->assertForbidden();
        $this->actingAs($editor)->patch('/settings/tiers-visibility', [])->assertForbidden();
    }

    public function test_reader_cannot_view_or_manage_tiers(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($reader)->get('/settings/tiers')->assertForbidden();
    }
}
