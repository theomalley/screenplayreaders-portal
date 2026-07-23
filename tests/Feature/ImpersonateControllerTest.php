<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_impersonate_a_non_admin(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'reader']);

        $response = $this->actingAs($admin)->post("/admin/impersonate/{$reader->id}");

        $response->assertRedirect(route('assignments.index'));
        $this->assertAuthenticatedAs($reader->fresh());
    }

    public function test_admin_cannot_impersonate_another_admin(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post("/admin/impersonate/{$otherAdmin->id}")->assertForbidden();
    }

    public function test_editor_cannot_impersonate_anyone(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $reader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($editor)->post("/admin/impersonate/{$reader->id}")->assertForbidden();
    }

    public function test_reader_cannot_impersonate_anyone(): void
    {
        $reader1 = User::factory()->create(['role' => 'reader']);
        $reader2 = User::factory()->create(['role' => 'reader']);

        $this->actingAs($reader1)->post("/admin/impersonate/{$reader2->id}")->assertForbidden();
    }
}
