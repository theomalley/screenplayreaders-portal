<?php

namespace Tests\Feature;

use App\Models\Karen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KarenControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeKaren(array $overrides = []): Karen
    {
        return Karen::create(array_merge([
            'first_name'   => 'Karen',
            'last_name'    => 'Testerson',
            'email'        => 'karen@example.com',
            'notes'        => 'Screamed at a reader over a 78 score.',
            'flagged_date' => now()->toDateString(),
        ], $overrides));
    }

    public function test_admin_and_editor_can_view_create_and_update(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);
        $karen  = $this->makeKaren();

        $this->actingAs($admin)->get('/karens')->assertOk();
        $this->actingAs($editor)->get('/karens')->assertOk();

        $this->actingAs($editor)
            ->postJson('/karens', ['first_name' => 'New', 'last_name' => 'Karen', 'email' => 'new@example.com'])
            ->assertOk();
        $this->assertDatabaseHas('karens', ['email' => 'new@example.com']);

        $this->actingAs($admin)
            ->patchJson("/karens/{$karen->id}", ['first_name' => 'Updated', 'last_name' => $karen->last_name, 'email' => $karen->email])
            ->assertOk();
        $this->assertDatabaseHas('karens', ['id' => $karen->id, 'first_name' => 'Updated']);
    }

    public function test_reader_cannot_view_or_manage_the_karen_list(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $karen  = $this->makeKaren();

        $this->actingAs($reader)->get('/karens')->assertForbidden();
        $this->actingAs($reader)->postJson('/karens', ['first_name' => 'X'])->assertForbidden();
        $this->actingAs($reader)->patchJson("/karens/{$karen->id}", ['first_name' => 'X'])->assertForbidden();
        $this->actingAs($reader)->delete("/karens/{$karen->id}")->assertForbidden();
    }

    public function test_only_admin_can_delete_a_karen(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);
        $k1     = $this->makeKaren();
        $k2     = $this->makeKaren(['email' => 'other@example.com']);

        $this->actingAs($editor)->delete("/karens/{$k1->id}")->assertForbidden();
        $this->actingAs($admin)->delete("/karens/{$k2->id}")->assertRedirect();
        $this->assertDatabaseMissing('karens', ['id' => $k2->id]);
    }

    public function test_view_permission_toggle_blocks_editor_when_disabled(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        \App\Models\Setting::setValue(\App\Support\Permission::settingKey('karens', 'editor'), '0');

        $this->actingAs($editor)->get('/karens')->assertForbidden();
    }

    public function test_matches_by_email_case_insensitively(): void
    {
        $this->makeKaren(['email' => 'Karen@Example.com', 'first_name' => null, 'last_name' => null]);

        $match = Karen::matchFor(null, null, 'karen@example.com');

        $this->assertNotNull($match);
    }

    public function test_matches_by_first_and_last_name_together(): void
    {
        $this->makeKaren(['email' => null, 'first_name' => 'Jane', 'last_name' => 'Doe']);

        $this->assertNotNull(Karen::matchFor('jane', 'doe', null));
        $this->assertNull(Karen::matchFor('jane', null, null));
    }

    public function test_no_match_returns_null(): void
    {
        $this->makeKaren();

        $this->assertNull(Karen::matchFor('Totally', 'Different', 'nobody@example.com'));
    }
}
