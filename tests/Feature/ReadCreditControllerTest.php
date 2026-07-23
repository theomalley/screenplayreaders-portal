<?php

namespace Tests\Feature;

use App\Models\ReadCreditPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadCreditControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makePackage(): ReadCreditPackage
    {
        return ReadCreditPackage::create([
            'customer_email'    => 'test@example.com',
            'customer_name'     => 'Test Customer',
            'woo_order_number'  => 'TEST-' . random_int(100000, 999999),
            'product_id'        => 1,
            'credits_purchased' => 5,
            'credits_remaining' => 5,
            'status'            => ReadCreditPackage::STATUS_ACTIVE,
            'expires_at'        => now()->addYear(),
        ]);
    }

    public function test_admin_and_editor_can_view_create_and_edit(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);
        $pkg    = $this->makePackage();

        $this->actingAs($admin)->get('/read-credits')->assertOk();
        $this->actingAs($admin)->get('/read-credits/create')->assertOk();
        $this->actingAs($admin)->get("/read-credits/{$pkg->id}/edit")->assertOk();
        $this->actingAs($editor)->get('/read-credits')->assertOk();
        $this->actingAs($editor)->get('/read-credits/create')->assertOk();
        $this->actingAs($editor)->get("/read-credits/{$pkg->id}/edit")->assertOk();
    }

    public function test_reader_cannot_view_create_or_edit(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $pkg    = $this->makePackage();

        $this->actingAs($reader)->get('/read-credits')->assertForbidden();
        $this->actingAs($reader)->get('/read-credits/create')->assertForbidden();
        $this->actingAs($reader)->get("/read-credits/{$pkg->id}/edit")->assertForbidden();
    }

    public function test_admin_and_editor_can_store_and_update(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($admin)->post('/read-credits', [
            'customer_email' => 'a@example.com',
            'customer_name'  => 'A',
            'credits'        => 3,
            'expires_at'     => now()->addYear()->toDateString(),
        ])->assertRedirect();

        $pkg = $this->makePackage();
        $this->actingAs($editor)->patch("/read-credits/{$pkg->id}", [
            'credits_remaining' => 2,
            'expires_at'        => now()->addYear()->toDateString(),
            'status'            => ReadCreditPackage::STATUS_ACTIVE,
            'adjustment_note'   => 'test',
        ])->assertRedirect();
        $this->assertEquals(2, $pkg->fresh()->credits_remaining);
    }

    public function test_reader_cannot_store_or_update(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $pkg    = $this->makePackage();

        $this->actingAs($reader)->post('/read-credits', [
            'customer_email' => 'a@example.com',
            'customer_name'  => 'A',
            'credits'        => 3,
            'expires_at'     => now()->addYear()->toDateString(),
        ])->assertForbidden();

        $this->actingAs($reader)->patch("/read-credits/{$pkg->id}", [
            'credits_remaining' => 2,
            'expires_at'        => now()->addYear()->toDateString(),
            'status'            => ReadCreditPackage::STATUS_ACTIVE,
            'adjustment_note'   => 'test',
        ])->assertForbidden();
    }
}
