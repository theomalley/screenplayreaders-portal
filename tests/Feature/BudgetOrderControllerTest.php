<?php

namespace Tests\Feature;

use App\Jobs\GenerateBudgetFiles;
use App\Models\Budget\BudgetOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BudgetOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeBudgetOrder(): BudgetOrder
    {
        return BudgetOrder::create([
            'customer_name'  => 'Test Customer',
            'customer_email' => 'test@example.com',
            'budget_amount'  => 1000,
            'budget_class'   => 1,
        ]);
    }

    public function test_admin_and_editor_can_view_index_and_show(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);
        $order  = $this->makeBudgetOrder();

        $this->actingAs($admin)->get('/budget-orders')->assertOk();
        $this->actingAs($admin)->get("/budget-orders/{$order->id}")->assertOk();
        $this->actingAs($editor)->get('/budget-orders')->assertOk();
        $this->actingAs($editor)->get("/budget-orders/{$order->id}")->assertOk();
    }

    public function test_reader_cannot_view_index_or_show(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $order  = $this->makeBudgetOrder();

        $this->actingAs($reader)->get('/budget-orders')->assertForbidden();
        $this->actingAs($reader)->get("/budget-orders/{$order->id}")->assertForbidden();
    }

    public function test_admin_and_editor_can_download_and_regenerate(): void
    {
        Queue::fake();
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);
        $order  = $this->makeBudgetOrder();

        // No drive_pdf_id/drive_xlsx_id set — controller redirects with an error before
        // touching Google Docs/Spaces, which is exactly what keeps this test network-free.
        $this->assertNotEquals(403, $this->actingAs($admin)->get("/budget-orders/{$order->id}/download-pdf")->getStatusCode());
        $this->assertNotEquals(403, $this->actingAs($admin)->get("/budget-orders/{$order->id}/download-xlsx")->getStatusCode());
        $this->actingAs($editor)->post("/budget-orders/{$order->id}/regenerate")->assertRedirect();

        Queue::assertPushed(GenerateBudgetFiles::class);
    }

    public function test_reader_cannot_download_or_regenerate(): void
    {
        Queue::fake();
        $reader = User::factory()->create(['role' => 'reader']);
        $order  = $this->makeBudgetOrder();

        $this->actingAs($reader)->get("/budget-orders/{$order->id}/download-pdf")->assertForbidden();
        $this->actingAs($reader)->get("/budget-orders/{$order->id}/download-xlsx")->assertForbidden();
        $this->actingAs($reader)->post("/budget-orders/{$order->id}/regenerate")->assertForbidden();
    }

    public function test_only_admin_can_bulk_delete(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);
        $order  = $this->makeBudgetOrder();

        $this->actingAs($editor)->post('/budget-orders/bulk-delete', ['ids' => [$order->id]])->assertForbidden();
        $this->actingAs($admin)->post('/budget-orders/bulk-delete', ['ids' => [$order->id]])->assertRedirect();
        $this->assertDatabaseMissing('budget_orders', ['id' => $order->id]);
    }
}
