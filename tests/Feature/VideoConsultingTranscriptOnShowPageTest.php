<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoConsultingTranscriptOnShowPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompletedVideoConsultingAssignment(?string $transcript): Assignment
    {
        return Assignment::create([
            'order_number'               => 'TEST-VC-' . random_int(100000, 999999),
            'vendor'                     => 'sr',
            'assignment_type'            => Assignment::TYPE_VIDEO_CONSULTING,
            'script_title'               => '30 Minutes Video Consulting Call',
            'writer_name'                => 'N/A (test booking)',
            'pay_rate'                   => 50,
            'status'                     => Assignment::STATUS_COMPLETED,
            'submitted_at'               => now(),
            'is_test'                    => true,
            'video_consulting_transcript' => $transcript,
        ]);
    }

    public function test_archived_video_consulting_assignment_shows_its_transcript(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $assignment = $this->makeCompletedVideoConsultingAssignment('Speaker A: Hello there.');

        $response = $this->actingAs($admin)->get("/assignments/{$assignment->id}");

        $response->assertOk();
        $response->assertSee('Call Transcript');
        $response->assertSee('Speaker A: Hello there.');
    }

    public function test_video_consulting_assignment_with_no_transcript_yet_shows_no_transcript_section(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $assignment = $this->makeCompletedVideoConsultingAssignment(null);

        $response = $this->actingAs($admin)->get("/assignments/{$assignment->id}");

        $response->assertOk();
        $response->assertDontSee('Call Transcript');
    }

    public function test_coverage_assignment_never_shows_a_transcript_section(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $assignment = Assignment::create([
            'order_number'          => 'QC-COVERAGE-' . random_int(1000, 9999),
            'script_title'          => 'Test Script',
            'writer_name'           => 'Test Writer',
            'page_count'            => 100,
            'pay_rate'              => 50,
            'status'                => Assignment::STATUS_COMPLETED,
            'drive_coverage_doc_id' => 'doc-123',
            'submitted_at'          => now(),
            'is_test'               => true,
        ]);

        $response = $this->actingAs($admin)->get("/assignments/{$assignment->id}");

        $response->assertOk();
        $response->assertDontSee('Call Transcript');
    }
}
