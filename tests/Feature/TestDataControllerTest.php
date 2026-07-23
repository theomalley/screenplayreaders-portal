<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestDataControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_is_not_forbidden_from_the_test_data_page(): void
    {
        // Not assertOk(): the full view needs a built Vite manifest, which is a frontend-
        // build concern unrelated to authorization. What matters here is that the Gate
        // lets an admin past the 403 boundary.
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin/test-data');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_admin_can_toggle_auto_reset(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/admin/test-data/auto-reset', ['enabled' => '1'])->assertRedirect();
    }

    public function test_editor_is_forbidden_from_every_test_data_action(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get('/admin/test-data')->assertForbidden();
        $this->actingAs($editor)->post('/admin/test-data/seed')->assertForbidden();
        $this->actingAs($editor)->post('/admin/test-data/reset')->assertForbidden();
        $this->actingAs($editor)->delete('/admin/test-data')->assertForbidden();
        $this->actingAs($editor)->post('/admin/test-data/script')->assertForbidden();
        $this->actingAs($editor)->post('/admin/test-data/auto-reset')->assertForbidden();
    }

    public function test_reader_is_forbidden_from_every_test_data_action(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($reader)->get('/admin/test-data')->assertForbidden();
        $this->actingAs($reader)->post('/admin/test-data/seed')->assertForbidden();
        $this->actingAs($reader)->post('/admin/test-data/reset')->assertForbidden();
        $this->actingAs($reader)->delete('/admin/test-data')->assertForbidden();
        $this->actingAs($reader)->post('/admin/test-data/script')->assertForbidden();
        $this->actingAs($reader)->post('/admin/test-data/auto-reset')->assertForbidden();
    }
}
