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

    public function test_checking_a_cell_grants_both_view_and_accept_for_a_normal_tier(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tier1 = Tier::create(['name' => 'Tier 1', 'position' => 1]);
        $tier2 = Tier::create(['name' => 'Tier 2', 'position' => 2]);

        $this->actingAs($admin)
            ->patch('/settings/tiers-visibility', [
                'visibility' => [$tier1->id => [$tier2->id => '1']],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tier_cross_visibility', [
            'from_tier_id' => $tier1->id,
            'to_tier_id'   => $tier2->id,
            'can_view'     => true,
            'can_accept'   => true,
        ]);
    }

    public function test_unchecking_a_previously_granted_cell_clears_both_flags(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tier1 = Tier::create(['name' => 'Tier 1', 'position' => 1]);
        $tier2 = Tier::create(['name' => 'Tier 2', 'position' => 2]);

        \App\Models\TierCrossVisibility::create([
            'from_tier_id' => $tier1->id,
            'to_tier_id'   => $tier2->id,
            'can_view'     => true,
            'can_accept'   => true,
        ]);

        // Omitting the pair from the submitted matrix means every unchecked box on the form.
        $this->actingAs($admin)
            ->patch('/settings/tiers-visibility', ['visibility' => []])
            ->assertRedirect();

        $this->assertDatabaseHas('tier_cross_visibility', [
            'from_tier_id' => $tier1->id,
            'to_tier_id'   => $tier2->id,
            'can_view'     => false,
            'can_accept'   => false,
        ]);
    }

    public function test_checking_an_onboarding_row_grants_view_only_never_accept(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $onboarding = Tier::create(['name' => 'Onboarding', 'position' => 0, 'is_onboarding' => true]);
        $tier1      = Tier::create(['name' => 'Tier 1', 'position' => 1]);

        $this->actingAs($admin)
            ->patch('/settings/tiers-visibility', [
                'visibility' => [$onboarding->id => [$tier1->id => '1']],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tier_cross_visibility', [
            'from_tier_id' => $onboarding->id,
            'to_tier_id'   => $tier1->id,
            'can_view'     => true,
            'can_accept'   => false,
        ]);
    }

    public function test_newly_created_tier_appears_in_the_visibility_matrix_with_no_grants(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Tier::create(['name' => 'Tier 1', 'position' => 1]);

        $this->actingAs($admin)->post('/settings/tiers', ['name' => 'Tier 2'])->assertRedirect();
        $newTier = Tier::where('name', 'Tier 2')->firstOrFail();

        $response = $this->actingAs($admin)->get('/settings/tiers');
        $response->assertOk();
        $response->assertSee('Tier 2');

        // No cross-visibility row exists yet for the new tier either direction — TierAccess
        // treats a missing row as "no access", so this is a safe default requiring no migration.
        $this->assertDatabaseMissing('tier_cross_visibility', ['from_tier_id' => $newTier->id]);
        $this->assertDatabaseMissing('tier_cross_visibility', ['to_tier_id' => $newTier->id]);
    }
}
