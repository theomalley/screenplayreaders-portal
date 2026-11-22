<?php

namespace Tests\Feature;

use App\Models\ScriptRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Regression coverage for the ScriptRegistrationWebhookController fix (2026-11-22):
 * a duplicate-key violation on one payload used to roll back the whole batch with an
 * uncaught 500, instead of being treated as "already handled" like the pre-transaction
 * idempotency check already does for a fully-retried webhook.
 */
class ScriptRegistrationWebhookDuplicateKeyTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(): array
    {
        config(['services.portal.webhook_secret' => 'test-secret']);

        return ['Authorization' => 'Bearer test-secret'];
    }

    public function test_a_duplicate_key_on_one_payload_does_not_roll_back_the_rest_of_the_batch(): void
    {
        Queue::fake();

        // The idempotency check's "existing registration_ids" list is computed ONCE before
        // the loop and never updated as the loop itself creates rows — so a batch that
        // (legitimately or via a retried/duplicated webhook delivery) lists the SAME
        // registration_id twice reproduces the exact gap a true concurrent request would:
        // the first occurrence creates the row; the second's create() hits the real unique
        // constraint, because the pre-loop check has no way to know about it.
        $response = $this->withHeaders($this->authHeaders())->postJson('/api/script-registration', [
            'order_id'       => 'wc-900',
            'order_number'   => '900',
            'customer_name'  => 'Test Customer',
            'customer_email' => 'customer@example.com',
            'payloads'       => [
                [
                    'sr_registration_id' => 'SR-DUPE-1',
                    'sr_title'            => 'First Occurrence',
                ],
                [
                    'sr_registration_id' => 'SR-DUPE-1',
                    'sr_title'            => 'Duplicate Occurrence',
                ],
                [
                    'sr_registration_id' => 'SR-UNIQUE-2',
                    'sr_title'            => 'Unique Payload',
                ],
            ],
        ]);

        $response->assertStatus(202);

        // The unique payload after the colliding pair still committed.
        $this->assertDatabaseHas('script_registrations', [
            'registration_id' => 'SR-UNIQUE-2',
            'script_title'    => 'Unique Payload',
        ]);

        // Only the first occurrence of the colliding id was created — no second row,
        // no data corruption, and the whole batch wasn't rolled back because of it.
        $this->assertSame(
            1,
            ScriptRegistration::where('registration_id', 'SR-DUPE-1')->count()
        );
        $this->assertDatabaseHas('script_registrations', [
            'registration_id' => 'SR-DUPE-1',
            'script_title'    => 'First Occurrence',
        ]);
    }
}
