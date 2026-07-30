<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\OrderRevenue;
use App\Models\Setting;
use App\Models\User;
use App\Services\EditorCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QcCommissionPenaltyTest extends TestCase
{
    use RefreshDatabase;

    private function makeEditor(): User
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $editor->editorProfile()->create(['initials' => 'ED', 'first_name' => 'Test', 'last_name' => 'Editor']);

        return $editor;
    }

    private function makeOrder(User $editor, string $orderNumber, float $commission = 30): OrderRevenue
    {
        return OrderRevenue::create([
            'order_number'         => $orderNumber,
            'ordered_at'           => now(),
            'order_total'          => 100,
            'cog_commission'       => $commission,
            'cog_commission_base'  => $commission,
            'editor_id'            => $editor->id,
        ]);
    }

    private function makeAssignment(string $orderNumber, array $overrides = []): Assignment
    {
        return Assignment::create(array_merge([
            'order_number'          => $orderNumber,
            'script_title'          => 'Test Script',
            'writer_name'           => 'Test Writer',
            'page_count'            => 100,
            'pay_rate'              => 50,
            'status'                => Assignment::STATUS_QC,
            'drive_coverage_doc_id' => 'doc-' . random_int(1000, 9999),
            'submitted_at'          => now(),
            'is_test'               => true,
        ], $overrides));
    }

    public function test_editor_qcing_a_single_assignment_keeps_full_commission(): void
    {
        $editor     = $this->makeEditor();
        $order      = $this->makeOrder($editor, 'QC-SINGLE-FULL');
        $assignment = $this->makeAssignment('QC-SINGLE-FULL');

        $this->actingAs($editor)
            ->post("/qc/{$assignment->id}/approve")
            ->assertRedirect();

        $this->assertSame(30.0, (float) $order->fresh()->cog_commission);
    }

    public function test_someone_else_qcing_a_single_assignment_halves_commission(): void
    {
        $editor     = $this->makeEditor();
        $admin      = User::factory()->create(['role' => 'admin']);
        $order      = $this->makeOrder($editor, 'QC-SINGLE-HALF');
        $assignment = $this->makeAssignment('QC-SINGLE-HALF');

        $this->actingAs($admin)
            ->post("/qc/{$assignment->id}/approve")
            ->assertRedirect();

        $this->assertSame(15.0, (float) $order->fresh()->cog_commission);
    }

    public function test_editor_qcing_one_of_three_gets_one_third_commission(): void
    {
        $editor = $this->makeEditor();
        $admin  = User::factory()->create(['role' => 'admin']);
        $order  = $this->makeOrder($editor, 'QC-TRIPLE-PARTIAL', 30);

        $a1 = $this->makeAssignment('QC-TRIPLE-PARTIAL');
        $a2 = $this->makeAssignment('QC-TRIPLE-PARTIAL');
        $a3 = $this->makeAssignment('QC-TRIPLE-PARTIAL');

        // Editor personally QCs one; admin QCs the other two.
        $this->actingAs($editor)->post("/qc/{$a1->id}/approve")->assertRedirect();
        $this->actingAs($admin)->post("/qc/{$a2->id}/approve")->assertRedirect();
        $this->actingAs($admin)->post("/qc/{$a3->id}/approve")->assertRedirect();

        $this->assertSame(10.0, (float) $order->fresh()->cog_commission);
    }

    public function test_editor_qcing_none_of_three_gets_50_percent_floor(): void
    {
        $editor = $this->makeEditor();
        $admin  = User::factory()->create(['role' => 'admin']);
        $order  = $this->makeOrder($editor, 'QC-TRIPLE-ZERO', 30);

        $a1 = $this->makeAssignment('QC-TRIPLE-ZERO');
        $a2 = $this->makeAssignment('QC-TRIPLE-ZERO');
        $a3 = $this->makeAssignment('QC-TRIPLE-ZERO');

        $this->actingAs($admin)->post("/qc/{$a1->id}/approve")->assertRedirect();
        $this->actingAs($admin)->post("/qc/{$a2->id}/approve")->assertRedirect();
        $this->actingAs($admin)->post("/qc/{$a3->id}/approve")->assertRedirect();

        $this->assertSame(15.0, (float) $order->fresh()->cog_commission);
    }

    public function test_editor_qcing_all_of_two_gets_full_commission(): void
    {
        $editor = $this->makeEditor();
        $order  = $this->makeOrder($editor, 'QC-DOUBLE-FULL', 30);

        $a1 = $this->makeAssignment('QC-DOUBLE-FULL');
        $a2 = $this->makeAssignment('QC-DOUBLE-FULL');

        $this->actingAs($editor)->post("/qc/{$a1->id}/approve")->assertRedirect();
        $this->actingAs($editor)->post("/qc/{$a2->id}/approve")->assertRedirect();

        $this->assertSame(30.0, (float) $order->fresh()->cog_commission);
    }

    public function test_penalty_does_not_apply_until_every_sub_assignment_is_completed(): void
    {
        $editor = $this->makeEditor();
        $admin  = User::factory()->create(['role' => 'admin']);
        $order  = $this->makeOrder($editor, 'QC-TRIPLE-PENDING', 30);

        $a1 = $this->makeAssignment('QC-TRIPLE-PENDING');
        $this->makeAssignment('QC-TRIPLE-PENDING', ['status' => Assignment::STATUS_ASSIGNED]);
        $this->makeAssignment('QC-TRIPLE-PENDING', ['status' => Assignment::STATUS_ASSIGNED]);

        $this->actingAs($admin)->post("/qc/{$a1->id}/approve")->assertRedirect();

        // Two siblings are still in progress — no verdict yet, commission stays at base.
        $this->assertSame(30.0, (float) $order->fresh()->cog_commission);
    }

    public function test_toggle_disabled_keeps_full_commission_regardless_of_qc_actor(): void
    {
        Setting::setValue('qc_commission_penalty_enabled', '0');

        $editor     = $this->makeEditor();
        $admin      = User::factory()->create(['role' => 'admin']);
        $order      = $this->makeOrder($editor, 'QC-DISABLED');
        $assignment = $this->makeAssignment('QC-DISABLED');

        $this->actingAs($admin)
            ->post("/qc/{$assignment->id}/approve")
            ->assertRedirect();

        $this->assertSame(30.0, (float) $order->fresh()->cog_commission);
    }

    public function test_legacy_completed_order_with_no_recorded_qc_actor_is_never_penalized(): void
    {
        $editor = $this->makeEditor();
        $order  = $this->makeOrder($editor, 'QC-LEGACY-ORDER', 30);

        // Simulates an order approved before qc_completed_by_user_id existed: already
        // STATUS_COMPLETED, but no QC actor was ever recorded for it.
        $this->makeAssignment('QC-LEGACY-ORDER', [
            'status'                  => Assignment::STATUS_COMPLETED,
            'completed_at'            => now()->subDays(30),
            'qc_completed_by_user_id' => null,
        ]);

        // Simulates a later webhook resync (e.g. a refund) touching this old order.
        app(EditorCommissionService::class)->applyQcAdjustmentForOrder('QC-LEGACY-ORDER');

        $this->assertSame(30.0, (float) $order->fresh()->cog_commission);
    }

    public function test_cancelled_sibling_does_not_count_toward_denominator(): void
    {
        $editor = $this->makeEditor();
        $order  = $this->makeOrder($editor, 'QC-CANCELLED-SIBLING', 30);

        $a1 = $this->makeAssignment('QC-CANCELLED-SIBLING');
        $this->makeAssignment('QC-CANCELLED-SIBLING', ['status' => Assignment::STATUS_CANCELLED]);

        $this->actingAs($editor)->post("/qc/{$a1->id}/approve")->assertRedirect();

        // Only 1 countable sub-assignment, editor QC'd it -> full commission, not diluted by the cancelled one.
        $this->assertSame(30.0, (float) $order->fresh()->cog_commission);
    }
}
