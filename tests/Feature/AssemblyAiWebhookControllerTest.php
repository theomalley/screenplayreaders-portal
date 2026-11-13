<?php

namespace Tests\Feature;

use App\Jobs\ProcessVideoConsultingTranscript;
use App\Models\Assignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AssemblyAiWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssignment(array $overrides = []): Assignment
    {
        return Assignment::create(array_merge([
            'order_number'      => 'TEST-VC-' . random_int(100000, 999999),
            'vendor'            => 'sr',
            'assignment_type'   => Assignment::TYPE_VIDEO_CONSULTING,
            'script_title'      => '30 Minutes Video Consulting Call',
            'writer_name'       => 'N/A (test booking)',
            'pay_rate'          => 50,
            'status'            => Assignment::STATUS_QC,
            'is_test'           => true,
            'video_consulting_transcription_id' => 'transcript-123',
        ], $overrides));
    }

    private function postWebhook(array $payload, string $secret = 'test-webhook-secret'): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', '/api/assemblyai-webhook', [], [], [], [
            'CONTENT_TYPE'                             => 'application/json',
            'HTTP_X-AssemblyAI-Webhook-Secret'         => $secret,
        ], json_encode($payload));
    }

    public function test_valid_webhook_for_known_transcript_dispatches_processing_job(): void
    {
        config(['services.assemblyai.webhook_secret' => 'test-webhook-secret']);
        Queue::fake();
        $assignment = $this->makeAssignment();

        $this->postWebhook(['transcript_id' => 'transcript-123', 'status' => 'completed'])->assertOk();

        Queue::assertPushed(ProcessVideoConsultingTranscript::class, function ($job) use ($assignment) {
            return $job->assignmentId === $assignment->id
                && $job->transcriptId === 'transcript-123'
                && $job->status === 'completed';
        });
    }

    public function test_wrong_auth_header_is_rejected(): void
    {
        config(['services.assemblyai.webhook_secret' => 'test-webhook-secret']);
        Queue::fake();
        $this->makeAssignment();

        $this->postWebhook(['transcript_id' => 'transcript-123', 'status' => 'completed'], 'wrong-secret')
            ->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_unknown_transcript_id_is_a_no_op(): void
    {
        config(['services.assemblyai.webhook_secret' => 'test-webhook-secret']);
        Queue::fake();

        $this->postWebhook(['transcript_id' => 'does-not-exist', 'status' => 'completed'])->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_error_status_still_dispatches_so_it_can_be_logged(): void
    {
        config(['services.assemblyai.webhook_secret' => 'test-webhook-secret']);
        Queue::fake();
        $assignment = $this->makeAssignment();

        $this->postWebhook(['transcript_id' => 'transcript-123', 'status' => 'error'])->assertOk();

        Queue::assertPushed(ProcessVideoConsultingTranscript::class, function ($job) use ($assignment) {
            return $job->assignmentId === $assignment->id && $job->status === 'error';
        });
    }
}
