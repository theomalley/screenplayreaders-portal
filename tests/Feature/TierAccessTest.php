<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Tier;
use App\Models\TierCrossVisibility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TierAccessTest extends TestCase
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
            'order_number'  => 'TEST-' . random_int(100000, 999999),
            'script_title'  => 'Test Script',
            'writer_name'   => 'Test Writer',
            'page_count'    => 100,
            'pay_rate'      => 50,
            'status'        => Assignment::STATUS_UNASSIGNED,
            'unassigned_at' => now(),
        ], $overrides));

        $assignment->tiers()->sync($tierIds);

        return $assignment->fresh('tiers');
    }

    public function test_onboarding_reader_cannot_accept_outside_their_own_tier_even_with_view_only_cross_visibility(): void
    {
        $onboarding = Tier::create(['name' => 'Onboarding', 'position' => 0, 'is_onboarding' => true]);
        $tier1      = Tier::create(['name' => 'Tier 1', 'position' => 1]);

        TierCrossVisibility::create([
            'from_tier_id' => $onboarding->id,
            'to_tier_id'   => $tier1->id,
            'can_view'     => true,
            'can_accept'   => true, // deliberately wrong data — policy must still deny
        ]);

        $reader     = $this->makeReader([$onboarding->id]);
        $assignment = $this->makeAssignment([$tier1->id]);

        $this->assertTrue($reader->can('view', $assignment));
        $this->assertFalse($reader->can('accept', $assignment));
    }

    public function test_cross_tier_accept_is_gated_by_the_cross_visibility_toggle(): void
    {
        $tier1 = Tier::create(['name' => 'Tier 1', 'position' => 1]);
        $tier2 = Tier::create(['name' => 'Tier 2', 'position' => 2]);

        $reader     = $this->makeReader([$tier2->id]);
        $assignment = $this->makeAssignment([$tier1->id]);

        $this->assertFalse($reader->can('accept', $assignment));

        TierCrossVisibility::create([
            'from_tier_id' => $tier2->id,
            'to_tier_id'   => $tier1->id,
            'can_view'     => true,
            'can_accept'   => true,
        ]);

        $this->assertTrue($reader->fresh('readerProfile')->can('accept', $assignment));
    }

    public function test_type_restricted_tier_hides_disallowed_assignment_types(): void
    {
        $tier = Tier::create([
            'name'                      => 'Formatting Only',
            'position'                  => 1,
            'allowed_assignment_types'  => ['formatting'],
        ]);

        $reader = $this->makeReader([$tier->id]);

        $allowed    = $this->makeAssignment([$tier->id], ['assignment_type' => 'formatting']);
        $disallowed = $this->makeAssignment([$tier->id], ['assignment_type' => 'script_coverage']);

        $this->assertTrue($reader->can('accept', $allowed));
        $this->assertFalse($reader->can('accept', $disallowed));
    }

    public function test_multi_tier_assignment_is_reachable_via_either_tier(): void
    {
        $tier1 = Tier::create(['name' => 'Tier 1', 'position' => 1]);
        $tier2 = Tier::create(['name' => 'Tier 2', 'position' => 2]);

        $reader     = $this->makeReader([$tier2->id]);
        $assignment = $this->makeAssignment([$tier1->id, $tier2->id]);

        $this->assertTrue($reader->can('accept', $assignment));
    }

    public function test_zero_tier_assignment_is_invisible_to_every_reader(): void
    {
        $tier1 = Tier::create(['name' => 'Tier 1', 'position' => 1]);

        $reader     = $this->makeReader([$tier1->id]);
        $assignment = $this->makeAssignment([]);

        $this->assertFalse($reader->can('view', $assignment));
        $this->assertFalse($reader->can('accept', $assignment));
    }

    public function test_escalate_timeouts_transfers_tier_membership_after_the_configured_timeout(): void
    {
        $tier2 = Tier::create(['name' => 'Tier 2', 'position' => 2]);
        $tier1 = Tier::create([
            'name'                 => 'Tier 1',
            'position'             => 1,
            'timeout_hours'        => 24,
            'escalates_to_tier_id' => $tier2->id,
        ]);

        Carbon::setTestNow(now()->subHours(30));
        $assignment = $this->makeAssignment([$tier1->id]);
        Carbon::setTestNow();

        $this->artisan('tiers:escalate-timeouts')->assertExitCode(0);

        $assignment->refresh();
        $tierIds = $assignment->tiers->pluck('id')->all();

        $this->assertNotContains($tier1->id, $tierIds);
        $this->assertContains($tier2->id, $tierIds);
    }

    public function test_escalate_timeouts_leaves_recently_unassigned_assignments_alone(): void
    {
        $tier2 = Tier::create(['name' => 'Tier 2', 'position' => 2]);
        $tier1 = Tier::create([
            'name'                 => 'Tier 1',
            'position'             => 1,
            'timeout_hours'        => 24,
            'escalates_to_tier_id' => $tier2->id,
        ]);

        $assignment = $this->makeAssignment([$tier1->id]); // unassigned_at = now()

        $this->artisan('tiers:escalate-timeouts')->assertExitCode(0);

        $assignment->refresh();
        $this->assertEquals([$tier1->id], $assignment->tiers->pluck('id')->all());
    }
}
