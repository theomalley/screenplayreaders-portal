<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeEditor(): User
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $editor->editorProfile()->create([
            'initials'   => 'ED',
            'first_name' => 'Test',
            'last_name'  => 'Editor',
        ]);

        return $editor;
    }

    public function test_admin_can_view_editor_index_and_create_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/editors')->assertOk();
        $this->actingAs($admin)->get('/admin/editors/create')->assertOk();
    }

    public function test_editor_cannot_view_editor_index_or_create_page(): void
    {
        $editor = $this->makeEditor();

        $this->actingAs($editor)->get('/admin/editors')->assertForbidden();
        $this->actingAs($editor)->get('/admin/editors/create')->assertForbidden();
    }

    public function test_admin_can_edit_an_editor_profile(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = $this->makeEditor();

        $this->actingAs($admin)->get("/admin/editors/{$editor->id}/edit")->assertOk();
    }

    public function test_another_editor_cannot_edit_an_editor_profile_by_default(): void
    {
        $editor      = $this->makeEditor();
        $otherEditor = $this->makeEditor();

        $this->actingAs($editor)->get("/admin/editors/{$otherEditor->id}/edit")->assertForbidden();
    }

    public function test_reader_cannot_edit_an_editor_profile(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $editor = $this->makeEditor();

        $this->actingAs($reader)->get("/admin/editors/{$editor->id}/edit")->assertForbidden();
    }

    public function test_editing_a_reader_via_the_editor_route_returns_not_found(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($admin)->get("/admin/editors/{$reader->id}/edit")->assertNotFound();
    }

    public function test_admin_can_update_editor_rates(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = $this->makeEditor();

        $this->actingAs($admin)
            ->patch("/admin/editors/{$editor->id}/rates", ['editor_commission' => 10])
            ->assertRedirect();

        $this->assertEquals(10, $editor->editorProfile->fresh()->editor_commission);
    }

    public function test_non_admin_cannot_update_editor_rates(): void
    {
        $editor = $this->makeEditor();

        $this->actingAs($editor)
            ->patch("/admin/editors/{$editor->id}/rates", ['editor_commission' => 10])
            ->assertForbidden();
    }

    public function test_admin_can_delete_an_editor(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = $this->makeEditor();

        $this->actingAs($admin)->delete("/admin/editors/{$editor->id}")->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $editor->id]);
    }

    public function test_non_admin_cannot_delete_an_editor_by_default(): void
    {
        $editor      = $this->makeEditor();
        $otherEditor = $this->makeEditor();

        $this->actingAs($editor)->delete("/admin/editors/{$otherEditor->id}")->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $otherEditor->id]);
    }
}
