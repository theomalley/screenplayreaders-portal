<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Favorite Genres/Films (shown on the Script Consulting page) are self-serve on the Profile
 * page for any role that can appear there — readers, and now admin/editor too — see
 * ProfileController::updateFavorites().
 */
class ProfileFavoritesTest extends TestCase
{
    use RefreshDatabase;

    public function test_reader_can_save_favorites(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $this->actingAs($reader)
            ->patch('/profile/favorites', ['favorite_genres' => ['Drama', 'Comedy'], 'favorite_films' => ['Chinatown']])
            ->assertRedirect();

        $profile = $reader->fresh('readerProfile')->readerProfile;
        $this->assertEquals(['Drama', 'Comedy'], $profile->favorite_genres);
        $this->assertEquals(['Chinatown'], $profile->favorite_films);
    }

    public function test_admin_can_save_favorites_without_a_pre_existing_reader_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assertNull($admin->readerProfile);

        $this->actingAs($admin)
            ->patch('/profile/favorites', ['favorite_genres' => ['Thriller'], 'favorite_films' => ['Heat']])
            ->assertRedirect();

        $profile = $admin->fresh('readerProfile')->readerProfile;
        $this->assertNotNull($profile);
        $this->assertEquals(['Thriller'], $profile->favorite_genres);
        $this->assertEquals(['Heat'], $profile->favorite_films);
    }

    public function test_editor_can_save_favorites(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $editor->editorProfile()->create(['initials' => 'ED', 'first_name' => 'Test', 'last_name' => 'Editor']);

        $this->actingAs($editor)
            ->patch('/profile/favorites', ['favorite_genres' => ['Horror']])
            ->assertRedirect();

        $this->assertEquals(['Horror'], $editor->fresh('readerProfile')->readerProfile->favorite_genres);
    }

    public function test_admins_own_profile_page_shows_the_favorites_card(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/profile')
            ->assertOk()
            ->assertSee('Favorite Genres');
    }
}
