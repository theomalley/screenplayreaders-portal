<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\FollowupQuestion;
use App\Models\FollowupToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowupQuestionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssignment(?User $reader = null): Assignment
    {
        return Assignment::create([
            'order_number'       => 'TEST-' . random_int(100000, 999999),
            'script_title'       => 'Test Script',
            'writer_name'        => 'Test Writer',
            'page_count'         => 100,
            'pay_rate'           => 50,
            'status'             => Assignment::STATUS_COMPLETED,
            'assigned_reader_id' => $reader?->id,
        ]);
    }

    private function makeFollowup(Assignment $assignment, string $status = FollowupQuestion::STATUS_PENDING): FollowupQuestion
    {
        $token = FollowupToken::create([
            'token'          => bin2hex(random_bytes(16)),
            'order_number'   => $assignment->order_number,
            'assignment_ids' => [$assignment->id],
            'expires_at'     => now()->addDays(10),
        ]);

        return FollowupQuestion::create([
            'followup_token_id' => $token->id,
            'assignment_id'      => $assignment->id,
            'order_number'       => $assignment->order_number,
            'customer_questions' => 'What is the theme?',
            'status'             => $status,
        ]);
    }

    public function test_admin_and_editor_can_update_complete_and_delete_a_followup(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $editor     = User::factory()->create(['role' => 'editor']);
        $assignment = $this->makeAssignment();
        $followup   = $this->makeFollowup($assignment);

        $this->actingAs($admin)->patch("/followups/{$followup->id}", ['edited_questions' => 'Updated'])->assertRedirect();

        $followup2 = $this->makeFollowup($assignment);
        $this->actingAs($editor)->post("/followups/{$followup2->id}/complete")->assertRedirect();

        $followup3 = $this->makeFollowup($assignment);
        $this->actingAs($admin)->delete("/followups/{$followup3->id}")->assertRedirect();
        $this->assertDatabaseMissing('followup_questions', ['id' => $followup3->id]);
    }

    public function test_reader_cannot_update_complete_or_delete_a_followup(): void
    {
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment();
        $followup   = $this->makeFollowup($assignment);

        $this->actingAs($reader)->patch("/followups/{$followup->id}", ['edited_questions' => 'x'])->assertForbidden();
        $this->actingAs($reader)->post("/followups/{$followup->id}/complete")->assertForbidden();
        $this->actingAs($reader)->delete("/followups/{$followup->id}")->assertForbidden();
    }

    public function test_admin_and_editor_can_view_followup_history(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $assignment = $this->makeAssignment();
        $this->makeFollowup($assignment);

        $this->actingAs($admin)->get("/followup-history/{$assignment->order_number}")->assertOk();
    }

    public function test_reader_cannot_view_followup_history(): void
    {
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment();
        $this->makeFollowup($assignment);

        $this->actingAs($reader)->get("/followup-history/{$assignment->order_number}")->assertForbidden();
    }

    public function test_only_admin_can_destroy_a_followup_token(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $editor     = User::factory()->create(['role' => 'editor']);
        $assignment = $this->makeAssignment();
        $followup   = $this->makeFollowup($assignment);

        $this->actingAs($editor)->delete("/followup-tokens/{$followup->followup_token_id}")->assertForbidden();
        $this->actingAs($admin)->delete("/followup-tokens/{$followup->followup_token_id}")->assertRedirect();
        $this->assertDatabaseMissing('followup_tokens', ['id' => $followup->followup_token_id]);
    }

    public function test_assigned_reader_can_respond_to_their_own_unanswered_followup(): void
    {
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment($reader);
        $followup   = $this->makeFollowup($assignment, FollowupQuestion::STATUS_UNANSWERED);

        $this->actingAs($reader)
            ->postJson("/followups/{$followup->id}/respond", ['response' => 'My answer'])
            ->assertOk()
            ->assertJson(['status' => 'answered']);
    }

    public function test_a_different_reader_cannot_respond(): void
    {
        $reader      = User::factory()->create(['role' => 'reader']);
        $otherReader = User::factory()->create(['role' => 'reader']);
        $assignment  = $this->makeAssignment($reader);
        $followup    = $this->makeFollowup($assignment, FollowupQuestion::STATUS_UNANSWERED);

        $this->actingAs($otherReader)
            ->postJson("/followups/{$followup->id}/respond", ['response' => 'x'])
            ->assertForbidden();
    }

    public function test_admin_cannot_respond_as_a_reader(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $assignment = $this->makeAssignment($admin);
        $followup   = $this->makeFollowup($assignment, FollowupQuestion::STATUS_UNANSWERED);

        $this->actingAs($admin)
            ->postJson("/followups/{$followup->id}/respond", ['response' => 'x'])
            ->assertForbidden();
    }
}
