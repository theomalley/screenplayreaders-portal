<?php

namespace Tests\Feature;

use App\Jobs\DownloadWherebyRecording;
use App\Models\Assignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WherebyWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private function sign(string $body, string $secret = 'test-secret'): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

        return "t={$timestamp},v1={$signature}";
    }

    private function makeAssignment(array $overrides = []): Assignment
    {
        return Assignment::create(array_merge([
            'order_number'      => 'TEST-VC-' . random_int(100000, 999999),
            'vendor'            => 'sr',
            'assignment_type'   => Assignment::TYPE_VIDEO_CONSULTING,
            'script_title'      => '30 Minutes Video Consulting Call',
            'writer_name'       => 'N/A (test booking)',
            'pay_rate'          => 50,
            'status'            => Assignment::STATUS_ASSIGNED,
            'is_test'           => true,
            'whereby_room_name' => '/room-abc',
        ], $overrides));
    }

    public function test_valid_signature_with_known_room_dispatches_the_download_job(): void
    {
        config(['services.whereby.webhook_secret' => 'test-secret']);
        Queue::fake();
        $assignment = $this->makeAssignment();

        $payload = json_encode([
            'type' => 'recording.finished',
            'data' => ['roomName' => '/room-abc', 'recordingId' => 'rec-1', 'filename' => 'call.mp4', 'status' => 'completed'],
        ]);

        $this->call('POST', '/api/whereby-webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Whereby-Signature' => $this->sign($payload),
        ], $payload)->assertOk();

        Queue::assertPushed(DownloadWherebyRecording::class, function ($job) use ($assignment) {
            return $job->assignmentId === $assignment->id && $job->recordingId === 'rec-1';
        });
    }

    public function test_invalid_signature_is_rejected(): void
    {
        config(['services.whereby.webhook_secret' => 'test-secret']);
        Queue::fake();

        $payload = json_encode(['type' => 'recording.finished', 'data' => ['roomName' => '/room-abc', 'recordingId' => 'rec-1']]);

        $this->call('POST', '/api/whereby-webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Whereby-Signature' => 't=' . time() . ',v1=bogus',
        ], $payload)->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_unknown_room_name_is_a_no_op(): void
    {
        config(['services.whereby.webhook_secret' => 'test-secret']);
        Queue::fake();

        $payload = json_encode([
            'type' => 'recording.finished',
            'data' => ['roomName' => '/does-not-exist', 'recordingId' => 'rec-1', 'filename' => 'call.mp4'],
        ]);

        $this->call('POST', '/api/whereby-webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Whereby-Signature' => $this->sign($payload),
        ], $payload)->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_already_processed_recording_is_idempotent(): void
    {
        config(['services.whereby.webhook_secret' => 'test-secret']);
        Queue::fake();
        $this->makeAssignment(['video_consulting_recording_path' => 'video-consulting/already/there.mp4']);

        $payload = json_encode([
            'type' => 'recording.finished',
            'data' => ['roomName' => '/room-abc', 'recordingId' => 'rec-1', 'filename' => 'call.mp4'],
        ]);

        $this->call('POST', '/api/whereby-webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Whereby-Signature' => $this->sign($payload),
        ], $payload)->assertOk();

        Queue::assertNothingPushed();
    }
}
