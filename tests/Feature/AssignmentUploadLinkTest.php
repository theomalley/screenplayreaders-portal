<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the "Look Up Link" (non-destructive) and "Generate New Link" (destructive)
 * actions on each SR Assignment, which call the theme's sr/v1 upload-link /
 * generate-upload-link REST endpoints via WooCommerceService.
 */
class AssignmentUploadLinkTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssignment(array $overrides = []): Assignment
    {
        return Assignment::create(array_merge([
            'order_number'    => '123456',
            'vendor'          => 'sr',
            'assignment_type' => 'script_coverage',
            'script_title'    => 'Test Script',
            'writer_name'     => 'Test Writer',
            'page_count'      => 100,
            'pay_rate'        => 70,
            'status'          => Assignment::STATUS_ASSIGNED,
        ], $overrides));
    }

    public function test_admin_can_generate_an_upload_link(): void
    {
        Http::fake(fn () => Http::response(['success' => true, 'url' => 'https://screenplayreaders.com/upload/?order_id=123456&key=abc123'], 200));

        $admin      = User::factory()->create(['role' => 'admin']);
        $assignment = $this->makeAssignment();

        $this->actingAs($admin)
            ->post("/assignments/{$assignment->id}/generate-upload-link")
            ->assertRedirect();

        $assignment->refresh();
        $this->assertSame('https://screenplayreaders.com/upload/?order_id=123456&key=abc123', $assignment->upload_link_url);
        $this->assertNotNull($assignment->upload_link_generated_at);
    }

    public function test_admin_can_look_up_the_current_upload_link_without_invalidating_it(): void
    {
        Http::fake(fn () => Http::response(['success' => true, 'url' => 'https://screenplayreaders.com/upload/?order_id=123456&key=orderkey'], 200));

        $admin      = User::factory()->create(['role' => 'admin']);
        $assignment = $this->makeAssignment();

        $this->actingAs($admin)
            ->post("/assignments/{$assignment->id}/lookup-upload-link")
            ->assertRedirect();

        $assignment->refresh();
        $this->assertSame('https://screenplayreaders.com/upload/?order_id=123456&key=orderkey', $assignment->upload_link_url);
        // Lookup is non-destructive — it must never stamp upload_link_generated_at,
        // which is reserved for tracking when a NEW (invalidating) link was issued.
        $this->assertNull($assignment->upload_link_generated_at);

        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), '/orders/123456/upload-link'));
    }

    public function test_lookup_upload_link_surfaces_a_theme_error(): void
    {
        Http::fake(fn () => Http::response(['message' => 'Order not found.'], 404));

        $admin      = User::factory()->create(['role' => 'admin']);
        $assignment = $this->makeAssignment();

        $this->actingAs($admin)
            ->post("/assignments/{$assignment->id}/lookup-upload-link")
            ->assertRedirect();

        $this->assertNull($assignment->fresh()->upload_link_url);
    }

    public function test_reader_cannot_look_up_an_upload_link(): void
    {
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment();

        $this->actingAs($reader)
            ->post("/assignments/{$assignment->id}/lookup-upload-link")
            ->assertForbidden();
    }

    public function test_generate_upload_link_surfaces_a_theme_error(): void
    {
        Http::fake(fn () => Http::response(['message' => 'Order not found.'], 404));

        $admin      = User::factory()->create(['role' => 'admin']);
        $assignment = $this->makeAssignment();

        $response = $this->actingAs($admin)->post("/assignments/{$assignment->id}/generate-upload-link");

        $response->assertRedirect();
        $this->assertNull($assignment->fresh()->upload_link_url);
    }

    public function test_cannot_generate_an_upload_link_for_a_non_woocommerce_assignment(): void
    {
        Http::fake();

        $admin      = User::factory()->create(['role' => 'admin']);
        $assignment = $this->makeAssignment(['vendor' => 'wd', 'order_number' => 'MANUAL-1']);

        $this->actingAs($admin)
            ->post("/assignments/{$assignment->id}/generate-upload-link")
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_edit_page_renders_the_upload_link_card_in_its_new_position(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $assignment = $this->makeAssignment(['upload_link_url' => 'https://screenplayreaders.com/upload/?order_id=123456&key=abc123']);

        $html = $this->actingAs($admin)->get("/assignments/{$assignment->id}/edit")->assertOk()->getContent();

        $this->assertStringContainsString('Customer Upload Link', $html);
        $this->assertStringContainsString('https://screenplayreaders.com/upload/?order_id=123456&amp;key=abc123', $html);

        // Card order: Save Changes ("Actions") must appear before Customer Upload Link,
        // which must appear before Status &amp; Workflow (the moved block + new card both
        // belong directly under Save Changes, ahead of everything else in the sidebar).
        $actionsPos    = strpos($html, 'Save Changes');
        $uploadLinkPos = strpos($html, 'Customer Upload Link');
        $statusPos     = strpos($html, 'Status &amp; Workflow');

        $this->assertNotFalse($actionsPos);
        $this->assertNotFalse($uploadLinkPos);
        $this->assertNotFalse($statusPos);
        $this->assertTrue($actionsPos < $uploadLinkPos && $uploadLinkPos < $statusPos);
    }

    public function test_reader_cannot_generate_an_upload_link(): void
    {
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment();

        $this->actingAs($reader)
            ->post("/assignments/{$assignment->id}/generate-upload-link")
            ->assertForbidden();
    }
}
