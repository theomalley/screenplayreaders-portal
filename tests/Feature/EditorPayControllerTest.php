<?php

namespace Tests\Feature;

use App\Models\EditorPayAdjustment;
use App\Models\OrderRevenue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorPayControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeEditor(): User
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $editor->editorProfile()->create(['initials' => 'ED', 'first_name' => 'Test', 'last_name' => 'Editor']);

        return $editor;
    }

    private function makeOrder(User $editor): OrderRevenue
    {
        return OrderRevenue::create([
            'order_number'    => 'TEST-' . random_int(100000, 999999),
            'ordered_at'      => now(),
            'order_total'     => 100,
            'cog_commission'  => 20,
            'editor_id'       => $editor->id,
        ]);
    }

    private function makeAdjustment(User $editor, User $addedBy): EditorPayAdjustment
    {
        return EditorPayAdjustment::create([
            'user_id'          => $editor->id,
            'amount'           => 10,
            'description'      => 'Test adjustment',
            'added_by_user_id' => $addedBy->id,
        ]);
    }

    public function test_admin_can_mark_paid_clear_batch_and_add_adjustment(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = $this->makeEditor();

        $this->actingAs($admin)
            ->post("/editor-pay/{$editor->id}/mark-paid", ['scope' => 'past'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post("/editor-pay/{$editor->id}/clear-unpaid", ['scope' => 'past'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post("/editor-pay/{$editor->id}/adjustment", ['amount' => 5, 'description' => 'bonus'])
            ->assertRedirect();
        $this->assertDatabaseHas('editor_pay_adjustments', ['user_id' => $editor->id, 'description' => 'bonus']);
    }

    public function test_editor_cannot_mark_paid_clear_batch_or_add_adjustment(): void
    {
        $editor = $this->makeEditor();

        $this->actingAs($editor)->post("/editor-pay/{$editor->id}/mark-paid", ['scope' => 'past'])->assertForbidden();
        $this->actingAs($editor)->post("/editor-pay/{$editor->id}/clear-unpaid", ['scope' => 'past'])->assertForbidden();
        $this->actingAs($editor)->post("/editor-pay/{$editor->id}/adjustment", ['amount' => 5, 'description' => 'x'])->assertForbidden();
    }

    public function test_admin_can_mark_any_editor_unpaid(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = $this->makeEditor();

        $this->actingAs($admin)
            ->post("/editor-pay/{$editor->id}/mark-unpaid", ['paid_at' => now()->toDateString()])
            ->assertRedirect();
    }

    public function test_editor_can_mark_only_their_own_pay_unpaid(): void
    {
        $editor      = $this->makeEditor();
        $otherEditor = $this->makeEditor();

        $this->actingAs($editor)
            ->post("/editor-pay/{$editor->id}/mark-unpaid", ['paid_at' => now()->toDateString()])
            ->assertRedirect();

        $this->actingAs($editor)
            ->post("/editor-pay/{$otherEditor->id}/mark-unpaid", ['paid_at' => now()->toDateString()])
            ->assertForbidden();
    }

    public function test_reader_cannot_mark_anyone_unpaid(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $editor = $this->makeEditor();

        $this->actingAs($reader)
            ->post("/editor-pay/{$editor->id}/mark-unpaid", ['paid_at' => now()->toDateString()])
            ->assertForbidden();
    }

    public function test_admin_can_update_and_delete_commission(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = $this->makeEditor();
        $order  = $this->makeOrder($editor);

        $this->actingAs($admin)
            ->patch("/editor-pay/order/{$order->id}/commission", ['cog_commission' => 15])
            ->assertRedirect();
        $this->assertEquals(15, $order->fresh()->cog_commission);

        $this->actingAs($admin)
            ->delete("/editor-pay/order/{$order->id}/commission")
            ->assertRedirect();
        $this->assertEquals(0, $order->fresh()->cog_commission);
    }

    public function test_editor_cannot_update_or_delete_commission(): void
    {
        $editor = $this->makeEditor();
        $order  = $this->makeOrder($editor);

        $this->actingAs($editor)->patch("/editor-pay/order/{$order->id}/commission", ['cog_commission' => 15])->assertForbidden();
        $this->actingAs($editor)->delete("/editor-pay/order/{$order->id}/commission")->assertForbidden();
    }

    public function test_admin_can_delete_history_batch_all_history_and_adjustment(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = $this->makeEditor();
        $adjustment = $this->makeAdjustment($editor, $admin);

        $this->actingAs($admin)
            ->delete("/editor-pay/{$editor->id}/history/" . now()->toDateString())
            ->assertRedirect();

        $this->actingAs($admin)
            ->delete("/editor-pay/{$editor->id}/history")
            ->assertRedirect();

        $this->actingAs($admin)
            ->delete("/editor-pay/adjustment/{$adjustment->id}")
            ->assertRedirect();
        $this->assertDatabaseMissing('editor_pay_adjustments', ['id' => $adjustment->id]);
    }

    public function test_editor_cannot_delete_history_or_adjustments(): void
    {
        $editor     = $this->makeEditor();
        $adjustment = $this->makeAdjustment($editor, $editor);

        $this->actingAs($editor)->delete("/editor-pay/{$editor->id}/history/" . now()->toDateString())->assertForbidden();
        $this->actingAs($editor)->delete("/editor-pay/{$editor->id}/history")->assertForbidden();
        $this->actingAs($editor)->delete("/editor-pay/adjustment/{$adjustment->id}")->assertForbidden();
    }
}
