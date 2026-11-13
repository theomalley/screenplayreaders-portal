<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoConsultingJoinCallButtonTest extends TestCase
{
    use RefreshDatabase;

    private function makeVideoConsultingAssignment(string $status): Assignment
    {
        return Assignment::create([
            'order_number'       => 'TEST-VC-' . random_int(100000, 999999),
            'vendor'             => 'sr',
            'assignment_type'    => Assignment::TYPE_VIDEO_CONSULTING,
            'script_title'       => '30 Minutes Video Consulting Call',
            'writer_name'        => 'N/A (test booking)',
            'pay_rate'           => 50,
            'status'             => $status,
            'submitted_at'       => now(),
            'is_test'            => true,
            'whereby_room_url'   => 'https://example.whereby.com/test-room',
        ]);
    }

    public function test_join_call_button_shows_on_the_assignment_page_while_assigned(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $assignment = $this->makeVideoConsultingAssignment(Assignment::STATUS_ASSIGNED);

        $response = $this->actingAs($admin)->get("/assignments/{$assignment->id}");

        $response->assertOk();
        $response->assertSee('Join Call', false);
    }

    public function test_join_call_button_is_hidden_once_the_assignment_reaches_qc(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $assignment = $this->makeVideoConsultingAssignment(Assignment::STATUS_QC);

        $response = $this->actingAs($admin)->get("/assignments/{$assignment->id}");

        $response->assertOk();
        $response->assertDontSee('Join Call', false);
    }

    public function test_join_call_button_is_hidden_once_completed_archived(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $assignment = $this->makeVideoConsultingAssignment(Assignment::STATUS_COMPLETED);

        $response = $this->actingAs($admin)->get("/assignments/{$assignment->id}");

        $response->assertOk();
        $response->assertDontSee('Join Call', false);
    }

    public function test_join_call_button_is_hidden_on_the_assignments_index_once_in_qc(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->makeVideoConsultingAssignment(Assignment::STATUS_QC);

        $response = $this->actingAs($admin)->get('/assignments');

        $response->assertOk();
        $response->assertDontSee('Join Call', false);
    }
}
