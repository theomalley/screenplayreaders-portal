<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\OrderRevenue;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers per-reader SR pay-rate overrides (ReaderProfile::RATE_KEYS/effectiveRate()) and
 * their two downstream effects: Assignment's saving() hook re-pricing pay_rate once a real
 * reader is assigned, and ReaderCogsService syncing that back into the order's revenue row.
 */
class ReaderCustomRateTest extends TestCase
{
    use RefreshDatabase;

    private function makeReader(?float $scriptCoverageRate): User
    {
        $user = User::factory()->create(['role' => 'reader']);
        $user->readerProfile()->create([
            'initials'             => strtoupper(substr($user->name, 0, 2)),
            'first_name'           => 'Test',
            'last_name'            => 'Reader',
            'rate_script_coverage' => $scriptCoverageRate,
        ]);

        return $user->fresh('readerProfile');
    }

    public function test_reader_with_no_override_falls_back_to_the_sitewide_default(): void
    {
        $reader = $this->makeReader(null);

        $assignment = Assignment::create([
            'order_number'    => 'TEST-' . random_int(100000, 999999),
            'vendor'          => 'sr',
            'assignment_type' => 'script_coverage',
            'script_title'    => 'Test Script',
            'writer_name'     => 'Test Writer',
            'page_count'      => 100,
            'pay_rate'        => 0,
            'status'          => Assignment::STATUS_UNASSIGNED,
        ]);

        $assignment->update(['assigned_reader_id' => $reader->id, 'status' => Assignment::STATUS_ASSIGNED]);

        $this->assertSame(
            (float) Setting::RATE_DEFAULTS['rate_sr_script_coverage'],
            (float) $assignment->fresh()->pay_rate
        );
    }

    public function test_readers_own_override_wins_over_the_sitewide_default(): void
    {
        $reader = $this->makeReader(99.50);

        $assignment = Assignment::create([
            'order_number'    => 'TEST-' . random_int(100000, 999999),
            'vendor'          => 'sr',
            'assignment_type' => 'script_coverage',
            'script_title'    => 'Test Script',
            'writer_name'     => 'Test Writer',
            'page_count'      => 100,
            'pay_rate'        => 0,
            'status'          => Assignment::STATUS_UNASSIGNED,
        ]);

        $assignment->update(['assigned_reader_id' => $reader->id, 'status' => Assignment::STATUS_ASSIGNED]);

        $this->assertSame(99.50, (float) $assignment->fresh()->pay_rate);
    }

    public function test_assigning_a_reader_syncs_the_orders_real_cogs_into_order_revenue(): void
    {
        $reader = $this->makeReader(99.50);
        $orderNumber = 'TEST-' . random_int(100000, 999999);

        OrderRevenue::create([
            'order_number'      => $orderNumber,
            'ordered_at'        => now(),
            'order_total'       => 159.00,
            'cog_reader'        => 70.00, // the theme's flat placeholder estimate
            'cog_processing'    => 5.00,
            'cog_precommission' => 84.00,
            'cog_commission'    => 5.46,
            'cog_total'         => 80.46,
            'net_revenue'       => 78.54,
        ]);

        $assignment = Assignment::create([
            'order_number'    => $orderNumber,
            'vendor'          => 'sr',
            'assignment_type' => 'script_coverage',
            'script_title'    => 'Test Script',
            'writer_name'     => 'Test Writer',
            'page_count'      => 100,
            'pay_rate'        => 70.00,
            'status'          => Assignment::STATUS_UNASSIGNED,
        ]);

        $assignment->update(['assigned_reader_id' => $reader->id, 'status' => Assignment::STATUS_ASSIGNED]);

        $orderRevenue = OrderRevenue::where('order_number', $orderNumber)->first();

        $this->assertSame(99.50, (float) $orderRevenue->cog_reader);
        $this->assertSame(
            round(99.50 + 5.00 + 5.46, 2),
            (float) $orderRevenue->cog_total
        );
        $this->assertSame(
            round(159.00 - (99.50 + 5.00 + 5.46), 2),
            (float) $orderRevenue->net_revenue
        );
    }

    public function test_admin_assigned_reader_is_never_paid(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $assignment = Assignment::create([
            'order_number'    => 'TEST-' . random_int(100000, 999999),
            'vendor'          => 'sr',
            'assignment_type' => 'script_coverage',
            'script_title'    => 'Test Script',
            'writer_name'     => 'Test Writer',
            'page_count'      => 100,
            'pay_rate'        => 0,
            'status'          => Assignment::STATUS_UNASSIGNED,
        ]);

        $assignment->update(['assigned_reader_id' => $admin->id, 'status' => Assignment::STATUS_ASSIGNED]);

        $this->assertSame(0.0, (float) $assignment->fresh()->pay_rate);
    }
}
