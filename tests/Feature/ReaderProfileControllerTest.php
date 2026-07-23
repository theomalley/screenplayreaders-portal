<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReaderProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeReader(): User
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create([
            'initials'   => 'TR',
            'first_name' => 'Test',
            'last_name'  => 'Reader',
        ]);

        return $reader;
    }

    public function test_admin_and_editor_can_view_reader_index_and_create_page(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($admin)->get('/readers')->assertOk();
        $this->actingAs($admin)->get('/readers/create')->assertOk();
        $this->actingAs($editor)->get('/readers')->assertOk();
        $this->actingAs($editor)->get('/readers/create')->assertOk();
    }

    public function test_reader_cannot_view_reader_index_or_create_page(): void
    {
        $reader = $this->makeReader();

        $this->actingAs($reader)->get('/readers')->assertForbidden();
        $this->actingAs($reader)->get('/readers/create')->assertForbidden();
    }

    public function test_admin_can_edit_a_reader_profile(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $reader = $this->makeReader();

        $this->actingAs($admin)->get("/readers/{$reader->id}/edit")->assertOk();
    }

    public function test_reader_cannot_edit_another_reader_profile_by_default(): void
    {
        $reader      = $this->makeReader();
        $otherReader = $this->makeReader();

        $this->actingAs($reader)->get("/readers/{$otherReader->id}/edit")->assertForbidden();
    }

    public function test_editing_an_editor_via_the_reader_route_returns_not_found(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($admin)->get("/readers/{$editor->id}/edit")->assertNotFound();
    }

    public function test_admin_can_delete_a_reader(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $reader = $this->makeReader();

        $this->actingAs($admin)->delete("/readers/{$reader->id}")->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $reader->id]);
    }

    public function test_reader_cannot_delete_another_reader_by_default(): void
    {
        $reader      = $this->makeReader();
        $otherReader = $this->makeReader();

        $this->actingAs($reader)->delete("/readers/{$otherReader->id}")->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $otherReader->id]);
    }
}
