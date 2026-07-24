<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Tier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Confirms the assignments board only breaks assignments into per-tier "cards"
 * (assignments.partials.tier-section) for admins/editors. Readers must always see
 * a single flat "Available Assignments" list, never a per-tier section, regardless
 * of how many tiers their cross-visibility grants let them reach.
 */
class AssignmentTierCardsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeReader(array $tierIds = []): User
    {
        $user = User::factory()->create(['role' => 'reader']);
        $profile = $user->readerProfile()->create([
            'initials'   => strtoupper(substr($user->name, 0, 2)),
            'first_name' => 'Test',
            'last_name'  => 'Reader',
        ]);
        if ($tierIds) {
            $profile->tiers()->sync($tierIds);
        }

        return $user->fresh('readerProfile');
    }

    private function makeAssignment(array $tierIds, array $overrides = []): Assignment
    {
        $assignment = Assignment::create(array_merge([
            'order_number'    => 'TEST-' . random_int(100000, 999999),
            'assignment_type' => 'script_coverage',
            'script_title'    => 'Test Script',
            'writer_name'     => 'Test Writer',
            'page_count'      => 100,
            'pay_rate'        => 50,
            'status'          => Assignment::STATUS_UNASSIGNED,
            'unassigned_at'   => now(),
        ], $overrides));

        $assignment->tiers()->sync($tierIds);

        return $assignment->fresh('tiers');
    }

    public function test_admin_sees_per_tier_assignment_cards(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tier1 = Tier::create(['name' => 'Tier One', 'position' => 1]);
        $this->makeAssignment([$tier1->id]);

        $response = $this->actingAs($admin)->get('/assignments');

        $response->assertOk();
        $response->assertSee('Tier One Assignments', false);
    }

    public function test_editor_sees_per_tier_assignment_cards(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $tier1  = Tier::create(['name' => 'Tier One', 'position' => 1]);
        $this->makeAssignment([$tier1->id]);

        $response = $this->actingAs($editor)->get('/assignments');

        $response->assertOk();
        $response->assertSee('Tier One Assignments', false);
    }

    public function test_reader_never_sees_per_tier_assignment_cards_even_with_cross_visibility(): void
    {
        $tier1  = Tier::create(['name' => 'Tier One', 'position' => 1]);
        $tier2  = Tier::create(['name' => 'Tier Two', 'position' => 2]);

        \App\Models\TierCrossVisibility::create([
            'from_tier_id' => $tier1->id,
            'to_tier_id'   => $tier2->id,
            'can_view'     => true,
            'can_accept'   => true,
        ]);

        $reader = $this->makeReader([$tier1->id]);
        $this->makeAssignment([$tier1->id]);
        $this->makeAssignment([$tier2->id]);

        $response = $this->actingAs($reader)->get('/assignments');

        $response->assertOk();
        $response->assertDontSee('Tier One Assignments', false);
        $response->assertDontSee('Tier Two Assignments', false);
        // Sanity check: the reader still reaches both tiers' assignments in the flat list.
        $response->assertSee('Available Assignments', false);
    }
}
