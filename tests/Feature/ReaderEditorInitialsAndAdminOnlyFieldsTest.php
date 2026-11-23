<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the ReaderProfileController/EditorProfileController fixes
 * (2026-11-22): initials must be unique across both reader_profiles and
 * editor_profiles, and video_consulting_enabled/is_1099/requests_bypass_capacity are
 * admin-only toggles that an editor (even one granted readers.edit/editors.edit via
 * the Permissions screen) must not be able to set.
 */
class ReaderEditorInitialsAndAdminOnlyFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function makeReader(string $initials = 'TR'): User
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create([
            'initials'   => $initials,
            'first_name' => 'Test',
            'last_name'  => 'Reader',
        ]);

        return $reader;
    }

    private function makeEditor(string $initials = 'TE'): User
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $editor->editorProfile()->create([
            'initials'   => $initials,
            'first_name' => 'Test',
            'last_name'  => 'Editor',
        ]);

        return $editor;
    }

    private function readerStorePayload(array $overrides = []): array
    {
        return array_merge([
            'name'                  => 'New Reader',
            'email'                 => 'new-reader-' . random_int(1, 999999) . '@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'initials'              => 'NR',
            'first_name'            => 'New',
            'last_name'             => 'Reader',
            'availability'          => 'available',
        ], $overrides);
    }

    private function editorStorePayload(array $overrides = []): array
    {
        return array_merge([
            'name'                  => 'New Editor',
            'email'                 => 'new-editor-' . random_int(1, 999999) . '@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'initials'              => 'NE',
            'first_name'            => 'New',
            'last_name'             => 'Editor',
            'availability'          => 'available',
        ], $overrides);
    }

    public function test_a_new_readers_initials_cannot_collide_with_an_existing_readers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->makeReader('ZZ');

        $this->actingAs($admin)
            ->post('/readers', $this->readerStorePayload(['initials' => 'ZZ']))
            ->assertSessionHasErrors('initials');
    }

    public function test_a_new_readers_initials_cannot_collide_with_an_existing_editors(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->makeEditor('ZZ');

        $this->actingAs($admin)
            ->post('/readers', $this->readerStorePayload(['initials' => 'ZZ']))
            ->assertSessionHasErrors('initials');
    }

    public function test_a_new_editors_initials_cannot_collide_with_an_existing_readers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->makeReader('ZZ');

        $this->actingAs($admin)
            ->post('/admin/editors', $this->editorStorePayload(['initials' => 'ZZ']))
            ->assertSessionHasErrors('initials');
    }

    public function test_a_reader_can_keep_their_own_initials_when_updating_other_fields(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $reader = $this->makeReader('AB');

        $this->actingAs($admin)
            ->patch("/readers/{$reader->id}", [
                'initials'     => 'AB',
                'first_name'   => 'Test',
                'last_name'    => 'Reader',
                'email'        => $reader->email,
                'availability' => 'available',
            ])
            ->assertSessionDoesntHaveErrors('initials');
    }

    public function test_an_editor_with_edit_permission_cannot_enable_video_consulting_for_a_reader(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        \App\Models\Setting::setValue(\App\Support\Permission::settingKey('readers.edit', 'editor'), '1');
        $reader = $this->makeReader();

        $this->actingAs($editor)
            ->patch("/readers/{$reader->id}", [
                'initials'                  => 'TR',
                'first_name'                => 'Test',
                'last_name'                 => 'Reader',
                'email'                     => $reader->email,
                'availability'              => 'available',
                'video_consulting_enabled'  => '1',
                'is_1099'                   => '1',
                'requests_bypass_capacity'  => '1',
            ]);

        $profile = $reader->fresh()->readerProfile;
        $this->assertNotEquals('1', (string) ($profile->video_consulting_enabled ?? ''));
        $this->assertFalse((bool) $profile->is_1099);
        $this->assertFalse((bool) $profile->requests_bypass_capacity);
    }

    public function test_an_admin_can_enable_video_consulting_for_a_reader(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $reader = $this->makeReader();

        $this->actingAs($admin)
            ->patch("/readers/{$reader->id}", [
                'initials'                 => 'TR',
                'first_name'               => 'Test',
                'last_name'                => 'Reader',
                'email'                    => $reader->email,
                'availability'             => 'available',
                'video_consulting_enabled' => '1',
                'is_1099'                  => '1',
                'requests_bypass_capacity' => '1',
            ]);

        $profile = $reader->fresh()->readerProfile;
        $this->assertTrue((bool) $profile->is_1099);
        $this->assertTrue((bool) $profile->requests_bypass_capacity);
    }
}
