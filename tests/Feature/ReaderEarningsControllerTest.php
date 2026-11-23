<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\ReaderPayAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReaderEarningsControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Repro/regression for the "My Earnings" dashboard ignoring ReaderPayAdjustment rows
     * (audit finding 3): a reader with a manual adjustment must see it reflected in the
     * current period's Total Earned / Pending Pay figures, and once ReaderPayController's
     * markPaid() actually pays both out, Paid Out must match the same combined total.
     */
    public function test_current_period_totals_include_pay_adjustments(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $now = now();

        Assignment::create([
            'order_number'       => 'TEST-' . random_int(100000, 999999),
            'script_title'       => 'Test Script',
            'writer_name'        => 'Test Writer',
            'page_count'         => 100,
            'pay_rate'           => 50,
            'status'             => Assignment::STATUS_COMPLETED,
            'assigned_reader_id' => $reader->id,
            'completed_at'       => $now,
            'is_test'            => false,
        ]);

        ReaderPayAdjustment::create([
            'user_id'          => $reader->id,
            'amount'           => 10,
            'description'      => 'Test bonus',
            'added_by_user_id' => $admin->id,
            'created_at'       => $now,
        ]);

        // Before any payout: combined total/pending must be 60.00, nothing paid yet.
        $response = $this->actingAs($reader)->get('/reader-earnings');
        $response->assertOk();
        $response->assertSeeText('Test bonus');
        $response->assertSee('$60.00', false); // Total Earned
        $response->assertSee('$0.00', false);  // Paid Out

        // markPaid() is what actually pays assignments + adjustments out together — confirm
        // the dashboard's Paid Out figure after that matches the same combined total.
        $this->actingAs($admin)->post("/reader-pay/{$reader->id}/mark-paid")->assertRedirect();

        $response = $this->actingAs($reader)->get('/reader-earnings');
        $response->assertOk();
        $response->assertSee('$60.00', false); // Paid Out now matches markPaid()'s actual payout
    }
}
