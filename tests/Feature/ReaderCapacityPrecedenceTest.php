<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the capacity precedence flip: capacity_override is the global DEFAULT cap
 * applied to every reader; a reader's own max_concurrent_assignments, when set, is an
 * OVERRIDE of that default (not the other way around, as it worked previously).
 */
class ReaderCapacityPrecedenceTest extends TestCase
{
    use RefreshDatabase;

    private function makeReader(?int $maxConcurrentAssignments): User
    {
        $user = User::factory()->create(['role' => 'reader']);
        $user->readerProfile()->create([
            'initials'                    => strtoupper(substr($user->name, 0, 2)),
            'first_name'                  => 'Test',
            'last_name'                   => 'Reader',
            'max_concurrent_assignments'  => $maxConcurrentAssignments,
        ]);

        return $user->fresh('readerProfile');
    }

    private function assignActiveAssignments(User $reader, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Assignment::create([
                'order_number'       => 'TEST-' . random_int(100000, 999999),
                'script_title'       => 'Test Script',
                'writer_name'        => 'Test Writer',
                'page_count'         => 100,
                'pay_rate'           => 50,
                'status'             => Assignment::STATUS_ASSIGNED,
                'assigned_reader_id' => $reader->id,
                'accepted_at'        => now(),
            ]);
        }
    }

    public function test_reader_without_an_override_uses_the_global_default_cap(): void
    {
        Setting::setValue('capacity_override', 2);
        $reader = $this->makeReader(null);

        $this->assignActiveAssignments($reader, 2);

        $this->assertSame(2, $reader->readerProfile->effectiveCap());
        $this->assertTrue($reader->readerProfile->isAtCapacity());
    }

    public function test_readers_own_override_wins_over_the_global_default(): void
    {
        Setting::setValue('capacity_override', 2);
        $reader = $this->makeReader(5);

        $this->assignActiveAssignments($reader, 3);

        $this->assertSame(5, $reader->readerProfile->effectiveCap());
        $this->assertFalse($reader->readerProfile->isAtCapacity());
    }

    public function test_global_default_falls_back_to_three_when_never_configured(): void
    {
        $reader = $this->makeReader(null);

        $this->assertSame(3, $reader->readerProfile->effectiveCap());
    }

    public function test_reader_override_of_zero_is_respected_even_though_it_is_falsy(): void
    {
        Setting::setValue('capacity_override', 5);
        $reader = $this->makeReader(0);

        $this->assertSame(0, $reader->readerProfile->effectiveCap());
        $this->assertTrue($reader->readerProfile->isAtCapacity());
    }
}
