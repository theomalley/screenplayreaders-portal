<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Karen;
use App\Models\Tier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncomingAssignmentKarenAlertTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'order_number' => '90001',
            'service'      => 'coverage',
            'script_title' => 'Test Script',
            'writer_name'  => 'Some Writer',
            'page_count'   => '100',
            'rush'         => '0',
            'script'       => UploadedFile::fake()->create('script.pdf', 100, 'application/pdf'),
        ], $overrides);
    }

    private function authHeaders(): array
    {
        config(['services.portal.webhook_secret' => 'test-secret']);

        return ['Authorization' => 'Bearer test-secret'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();

        Tier::create(['name' => 'Tier 1', 'position' => 1, 'is_onboarding' => false]);
    }

    public function test_flags_assignment_when_customer_email_matches_karen_list(): void
    {
        Karen::create(['email' => 'repeat@offender.com', 'first_name' => 'Repeat', 'last_name' => 'Offender']);

        $this->withHeaders($this->authHeaders())->post('/api/incoming-assignment', $this->payload([
            'customer_first_name' => 'Someone',
            'customer_last_name'  => 'Else',
            'customer_email'      => 'repeat@offender.com',
        ]))->assertCreated();

        $assignment = Assignment::where('order_number', '90001')->firstOrFail();
        $this->assertTrue($assignment->karen_alert);
        $this->assertStringContainsString('Karen alert', $assignment->karen_alert_note);
    }

    public function test_flags_assignment_when_customer_name_matches_karen_list(): void
    {
        Karen::create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => null]);

        $this->withHeaders($this->authHeaders())->post('/api/incoming-assignment', $this->payload([
            'customer_first_name' => 'Jane',
            'customer_last_name'  => 'Doe',
            'customer_email'      => 'jane.doe.new.address@example.com',
        ]))->assertCreated();

        $assignment = Assignment::where('order_number', '90001')->firstOrFail();
        $this->assertTrue($assignment->karen_alert);
    }

    public function test_does_not_flag_assignment_for_unlisted_customer(): void
    {
        Karen::create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com']);

        $this->withHeaders($this->authHeaders())->post('/api/incoming-assignment', $this->payload([
            'customer_first_name' => 'John',
            'customer_last_name'  => 'Smith',
            'customer_email'      => 'john@example.com',
        ]))->assertCreated();

        $assignment = Assignment::where('order_number', '90001')->firstOrFail();
        $this->assertFalse($assignment->karen_alert);
        $this->assertNull($assignment->karen_alert_note);
    }

    public function test_older_payload_without_customer_fields_still_works_unflagged(): void
    {
        $this->withHeaders($this->authHeaders())->post('/api/incoming-assignment', $this->payload())
            ->assertCreated();

        $assignment = Assignment::where('order_number', '90001')->firstOrFail();
        $this->assertFalse($assignment->karen_alert);
    }
}
