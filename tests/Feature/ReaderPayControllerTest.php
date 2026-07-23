<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\ReaderPayAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReaderPayControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeReader(): User
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        return $reader;
    }

    private function makeCompletedAssignment(User $reader): Assignment
    {
        return Assignment::create([
            'order_number'        => 'TEST-' . random_int(100000, 999999),
            'script_title'        => 'Test Script',
            'writer_name'         => 'Test Writer',
            'page_count'          => 100,
            'pay_rate'            => 50,
            'status'              => Assignment::STATUS_COMPLETED,
            'assigned_reader_id'  => $reader->id,
        ]);
    }

    private function makeAdjustment(User $reader, User $addedBy): ReaderPayAdjustment
    {
        return ReaderPayAdjustment::create([
            'user_id'          => $reader->id,
            'amount'           => 10,
            'description'      => 'Test adjustment',
            'added_by_user_id' => $addedBy->id,
        ]);
    }

    public function test_admin_and_editor_can_mark_paid_mark_unpaid_and_add_adjustment(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);
        $reader = $this->makeReader();
        $this->makeCompletedAssignment($reader);

        $this->actingAs($admin)->post("/reader-pay/{$reader->id}/mark-paid")->assertRedirect();
        $this->actingAs($admin)->post("/reader-pay/{$reader->id}/mark-unpaid", ['paid_at' => now()->toDateString()])->assertRedirect();
        $this->actingAs($editor)->post("/reader-pay/{$reader->id}/adjustment", ['amount' => 5, 'description' => 'bonus'])->assertRedirect();
    }

    public function test_reader_cannot_mark_paid_mark_unpaid_or_add_adjustment(): void
    {
        $reader = $this->makeReader();

        $this->actingAs($reader)->post("/reader-pay/{$reader->id}/mark-paid")->assertForbidden();
        $this->actingAs($reader)->post("/reader-pay/{$reader->id}/mark-unpaid", ['paid_at' => now()->toDateString()])->assertForbidden();
        $this->actingAs($reader)->post("/reader-pay/{$reader->id}/adjustment", ['amount' => 5, 'description' => 'x'])->assertForbidden();
    }

    public function test_admin_can_delete_assignment_pay_clear_batch_and_remove_history(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $reader     = $this->makeReader();
        $assignment = $this->makeCompletedAssignment($reader);

        $this->actingAs($admin)->delete("/reader-pay/assignment/{$assignment->id}")->assertRedirect();
        $this->actingAs($admin)->post("/reader-pay/{$reader->id}/clear-unpaid")->assertRedirect();
        $this->actingAs($admin)->post("/reader-pay/{$reader->id}/remove-batch", ['paid_at' => now()->toDateString()])->assertRedirect();
    }

    public function test_editor_cannot_delete_assignment_pay_clear_batch_or_remove_history(): void
    {
        $editor     = User::factory()->create(['role' => 'editor']);
        $reader     = $this->makeReader();
        $assignment = $this->makeCompletedAssignment($reader);

        $this->actingAs($editor)->delete("/reader-pay/assignment/{$assignment->id}")->assertForbidden();
        $this->actingAs($editor)->post("/reader-pay/{$reader->id}/clear-unpaid")->assertForbidden();
        $this->actingAs($editor)->post("/reader-pay/{$reader->id}/remove-batch", ['paid_at' => now()->toDateString()])->assertForbidden();
    }

    public function test_admin_and_editor_can_delete_an_adjustment(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $reader     = $this->makeReader();
        $adjustment = $this->makeAdjustment($reader, $admin);

        $this->actingAs($admin)->delete("/reader-pay/adjustment/{$adjustment->id}")->assertRedirect();
        $this->assertDatabaseMissing('reader_pay_adjustments', ['id' => $adjustment->id]);
    }

    public function test_reader_cannot_delete_an_adjustment(): void
    {
        $reader     = $this->makeReader();
        $adjustment = $this->makeAdjustment($reader, $reader);

        $this->actingAs($reader)->delete("/reader-pay/adjustment/{$adjustment->id}")->assertForbidden();
    }
}
