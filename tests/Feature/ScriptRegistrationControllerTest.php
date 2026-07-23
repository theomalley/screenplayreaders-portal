<?php

namespace Tests\Feature;

use App\Models\ScriptRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScriptRegistrationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeRegistration(): ScriptRegistration
    {
        return ScriptRegistration::create([
            'woo_order_id'      => 'TEST-' . random_int(100000, 999999),
            'customer_name'     => 'Test Customer',
            'customer_email'    => 'test@example.com',
            'variation_id'      => ScriptRegistration::VAR_FREE_90,
            'variation_label'   => '90-Day Free',
            'registration_id'   => 'SR-' . random_int(100000, 999999),
            'script_title'      => 'Test Script',
            'type_of_work'      => 'Feature Screenplay',
            'author_first'      => 'Test',
            'author_last'       => 'Author',
            'street_address'    => '123 Test St',
            'city'              => 'Los Angeles',
            'state_or_province' => 'CA',
            'postal_or_zip'     => '90001',
            'country'           => 'United States',
            'phone'             => '555-0100',
            'email'             => 'test@example.com',
            'authcode'          => bin2hex(random_bytes(16)),
            'registered_at'     => now(),
            'status'            => ScriptRegistration::STATUS_COMPLETED,
        ]);
    }

    public function test_admin_and_editor_can_view_list_show_and_download(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);
        $reg    = $this->makeRegistration();

        $this->actingAs($admin)->get('/script-registrations')->assertOk();
        $this->actingAs($admin)->get("/script-registrations/{$reg->id}")->assertOk();
        $this->actingAs($editor)->get('/script-registrations')->assertOk();
        $this->actingAs($editor)->get("/script-registrations/{$reg->id}")->assertOk();

        // No drive_certificate_pdf_id/spaces_script_file_path set — controller redirects
        // with an error before touching Google Docs/Spaces, keeping this network-free.
        $this->assertNotEquals(403, $this->actingAs($admin)->get("/script-registrations/{$reg->id}/download")->getStatusCode());
        $this->assertNotEquals(403, $this->actingAs($admin)->get("/script-registrations/{$reg->id}/download-script")->getStatusCode());
    }

    public function test_reader_cannot_view_list_show_or_download(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reg    = $this->makeRegistration();

        $this->actingAs($reader)->get('/script-registrations')->assertForbidden();
        $this->actingAs($reader)->get("/script-registrations/{$reg->id}")->assertForbidden();
        $this->actingAs($reader)->get("/script-registrations/{$reg->id}/download")->assertForbidden();
        $this->actingAs($reader)->get("/script-registrations/{$reg->id}/download-script")->assertForbidden();
    }

    public function test_admin_and_editor_can_regenerate_certificate(): void
    {
        Queue::fake();
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);
        $reg    = $this->makeRegistration();

        $this->actingAs($admin)->post("/script-registrations/{$reg->id}/regenerate")->assertRedirect();
        $this->actingAs($editor)->post("/script-registrations/{$reg->id}/regenerate")->assertRedirect();
    }

    public function test_reader_cannot_regenerate_certificate(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reg    = $this->makeRegistration();

        $this->actingAs($reader)->post("/script-registrations/{$reg->id}/regenerate")->assertForbidden();
    }

    public function test_only_admin_can_regenerate_token_delete_bulk_delete_and_test_tools(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $reg   = $this->makeRegistration();

        $this->actingAs($admin)->post("/script-registrations/{$reg->id}/regenerate-token")->assertRedirect();
        $this->actingAs($admin)->get('/script-registrations-test')->assertOk();
        $this->actingAs($admin)->post('/script-registrations-test', [
            'test_email'   => 'a@example.com',
            'variation_id' => ScriptRegistration::VAR_FREE_90,
            'title'        => 'Test',
            'page_count'   => 100,
            'type_of_work' => 'Feature Screenplay',
            'author_first' => 'A',
            'author_last'  => 'B',
        ])->assertRedirect();

        $reg2 = $this->makeRegistration();
        $this->actingAs($admin)->post('/script-registrations/bulk-delete', ['ids' => [$reg2->id]])->assertRedirect();
        $this->assertDatabaseMissing('script_registrations', ['id' => $reg2->id]);

        $reg3 = $this->makeRegistration();
        $this->actingAs($admin)->delete("/script-registrations/{$reg3->id}")->assertRedirect();
        $this->assertDatabaseMissing('script_registrations', ['id' => $reg3->id]);
    }

    public function test_editor_cannot_regenerate_token_delete_bulk_delete_or_test_tools(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $reg    = $this->makeRegistration();

        $this->actingAs($editor)->post("/script-registrations/{$reg->id}/regenerate-token")->assertForbidden();
        $this->actingAs($editor)->get('/script-registrations-test')->assertForbidden();
        $this->actingAs($editor)->post('/script-registrations-test')->assertForbidden();
        $this->actingAs($editor)->post('/script-registrations/bulk-delete', ['ids' => [$reg->id]])->assertForbidden();
        $this->actingAs($editor)->delete("/script-registrations/{$reg->id}")->assertForbidden();
    }
}
