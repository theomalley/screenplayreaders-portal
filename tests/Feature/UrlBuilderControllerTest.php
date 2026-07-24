<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UrlBuilderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_editor_can_view_and_use_upload_lookup(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($admin)->get('/url-builder')->assertOk();
        $this->actingAs($editor)->get('/url-builder')->assertOk();

        // WooCommerceService will fail against an unconfigured store URL, but that's
        // caught and returned as a 502 JSON error, not a crash — fine for an auth-only test.
        $this->assertNotEquals(403, $this->actingAs($admin)->post('/url-builder/upload-lookup', ['order_id' => 123])->getStatusCode());
    }

    public function test_reader_cannot_view_or_use_upload_lookup(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($reader)->get('/url-builder')->assertForbidden();
        $this->actingAs($reader)->post('/url-builder/upload-lookup', ['order_id' => 123])->assertForbidden();
    }
}
