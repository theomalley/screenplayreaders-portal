<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Tier;
use App\Models\User;
use App\Support\TierAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentHiddenFromReaderTest extends TestCase
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

    public function test_hidden_reader_cannot_view_or_accept_an_otherwise_matching_assignment(): void
    {
        $tier1      = Tier::create(['name' => 'Tier 1', 'position' => 1]);
        $reader     = $this->makeReader([$tier1->id]);
        $assignment = $this->makeAssignment([$tier1->id], [
            'hidden_from_reader_ids' => [$reader->id],
        ]);

        $this->assertTrue($assignment->isHiddenFromReader($reader->id));
        $this->assertFalse($reader->can('view', $assignment));
        $this->assertFalse($reader->can('accept', $assignment));
    }

    public function test_hide_override_beats_tier_match_even_for_readers_with_full_cross_visibility(): void
    {
        $tier1  = Tier::create(['name' => 'Tier 1', 'position' => 1]);
        $reader = $this->makeReader([$tier1->id]);

        $assignment = $this->makeAssignment([$tier1->id], [
            'hidden_from_reader_ids' => [$reader->id],
        ]);

        // Sanity check: without the hide, this reader would normally be able to view/accept.
        $unhidden = $this->makeAssignment([$tier1->id]);
        $this->assertTrue($reader->can('view', $unhidden));
        $this->assertTrue($reader->can('accept', $unhidden));

        $this->assertFalse($reader->can('view', $assignment));
        $this->assertFalse($reader->can('accept', $assignment));
    }

    public function test_hide_is_scoped_to_the_specific_reader_not_the_whole_pool(): void
    {
        $tier1        = Tier::create(['name' => 'Tier 1', 'position' => 1]);
        $hiddenReader = $this->makeReader([$tier1->id]);
        $otherReader  = $this->makeReader([$tier1->id]);

        $assignment = $this->makeAssignment([$tier1->id], [
            'hidden_from_reader_ids' => [$hiddenReader->id],
        ]);

        $this->assertFalse($hiddenReader->can('view', $assignment));
        $this->assertTrue($otherReader->can('view', $assignment));
        $this->assertTrue($otherReader->can('accept', $assignment));
    }

    public function test_scope_available_excludes_assignments_hidden_from_the_reader(): void
    {
        $tier1  = Tier::create(['name' => 'Tier 1', 'position' => 1]);
        $reader = $this->makeReader([$tier1->id]);

        $hidden  = $this->makeAssignment([$tier1->id], ['hidden_from_reader_ids' => [$reader->id]]);
        $visible = $this->makeAssignment([$tier1->id]);

        $groups = TierAccess::reachableTierGroups($reader->readerProfile, forAccept: false);
        $ids    = Assignment::available($reader->id, $groups)->pluck('id');

        $this->assertFalse($ids->contains($hidden->id));
        $this->assertTrue($ids->contains($visible->id));
    }
}
