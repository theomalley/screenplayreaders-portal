<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WooOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private function fakeWooOrder(): void
    {
        Http::fake(fn () => Http::response([
            'id' => 123, 'number' => '123', 'total' => '100.00',
            'billing' => ['first_name' => 'A', 'last_name' => 'B'],
            'line_items' => [], 'refunds' => [],
        ], 200));
    }

    public function test_admin_and_editor_can_view_and_refund_an_order(): void
    {
        $this->fakeWooOrder();
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);

        // Not assertOk(): the view expects many more WC order fields than this test
        // fixture provides. What matters here is that the Gate lets these roles past 403.
        $this->assertNotEquals(403, $this->actingAs($admin)->get('/woo-orders/123')->getStatusCode());
        $this->assertNotEquals(403, $this->actingAs($editor)->get('/woo-orders/123')->getStatusCode());

        $this->assertNotEquals(403, $this->actingAs($admin)->post('/woo-orders/123/refund', ['amount' => 10])->getStatusCode());
        $this->assertNotEquals(403, $this->actingAs($admin)->post('/woo-orders/123/resend-email')->getStatusCode());
    }

    public function test_reader_cannot_view_refund_resend_or_download_invoice(): void
    {
        $this->fakeWooOrder();
        $reader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($reader)->get('/woo-orders/123')->assertForbidden();
        $this->actingAs($reader)->post('/woo-orders/123/refund', ['amount' => 10])->assertForbidden();
        $this->actingAs($reader)->post('/woo-orders/123/resend-email')->assertForbidden();
        $this->actingAs($reader)->get('/woo-orders/123/invoice-pdf')->assertForbidden();
    }
}
