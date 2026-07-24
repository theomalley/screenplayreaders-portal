<?php

namespace Tests\Feature;

use App\Models\OrderRevenue;
use App\Models\User;
use App\Services\GoogleDocsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrderLog(): OrderRevenue
    {
        return OrderRevenue::create([
            'order_number' => 'TEST-' . random_int(100000, 999999),
            'ordered_at'   => now(),
            'order_total'  => 100,
        ]);
    }

    public function test_admin_and_editor_can_view_index(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($admin)->get('/order-log')->assertOk();
        $this->actingAs($editor)->get('/order-log')->assertOk();
    }

    public function test_reader_cannot_view_index(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($reader)->get('/order-log')->assertForbidden();
    }

    public function test_admin_and_editor_can_download_invoice(): void
    {
        $this->mock(GoogleDocsService::class, function ($mock) {
            $mock->shouldReceive('generatePdfBytesAndCleanup')->andReturn('fake-pdf-bytes');
        });
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);
        $order  = $this->makeOrderLog();

        $this->actingAs($admin)->get("/order-log/{$order->id}/invoice-pdf")->assertOk();
        $this->actingAs($editor)->get("/order-log/{$order->id}/invoice-pdf")->assertOk();
    }

    public function test_reader_cannot_download_invoice(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $order  = $this->makeOrderLog();

        $this->actingAs($reader)->get("/order-log/{$order->id}/invoice-pdf")->assertForbidden();
    }

    public function test_only_admin_can_create_edit_delete_and_bulk_delete(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->makeOrderLog();

        $this->actingAs($admin)->get('/order-log/create')->assertOk();
        $this->actingAs($admin)->post('/order-log', [
            'order_number' => 'NEW-' . random_int(100000, 999999),
            'ordered_at'   => now()->toDateString(),
            'order_total'  => 50,
        ])->assertRedirect();
        $this->actingAs($admin)->get("/order-log/{$order->id}/edit")->assertOk();
        $this->actingAs($admin)->patch("/order-log/{$order->id}", [
            'order_number' => $order->order_number,
            'ordered_at'   => now()->toDateString(),
            'order_total'  => 75,
        ])->assertRedirect();

        $order2 = $this->makeOrderLog();
        $this->actingAs($admin)->post('/order-log/bulk-delete', ['ids' => [$order2->id]])->assertRedirect();
        $this->assertDatabaseMissing('order_revenues', ['id' => $order2->id]);

        $order3 = $this->makeOrderLog();
        $this->actingAs($admin)->delete("/order-log/{$order3->id}")->assertRedirect();
        $this->assertDatabaseMissing('order_revenues', ['id' => $order3->id]);
    }

    public function test_editor_cannot_create_edit_delete_or_bulk_delete(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $order  = $this->makeOrderLog();

        $this->actingAs($editor)->get('/order-log/create')->assertForbidden();
        $this->actingAs($editor)->post('/order-log', ['order_number' => 'X'])->assertForbidden();
        $this->actingAs($editor)->get("/order-log/{$order->id}/edit")->assertForbidden();
        $this->actingAs($editor)->patch("/order-log/{$order->id}", [])->assertForbidden();
        $this->actingAs($editor)->delete("/order-log/{$order->id}")->assertForbidden();
        $this->actingAs($editor)->post('/order-log/bulk-delete', ['ids' => [$order->id]])->assertForbidden();
    }
}
