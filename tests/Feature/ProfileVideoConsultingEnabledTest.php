<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An admin/editor opting into video consulting from their OWN Profile page, as opposed to
 * another admin editing their profile at /admin/editors/{user} — see
 * ProfileController::updateVideoConsultingEnabled().
 */
class ProfileVideoConsultingEnabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enable_video_consulting_for_themselves(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertNull($admin->readerProfile);

        $this->actingAs($admin)
            ->patch('/profile/video-consulting-enabled', ['video_consulting_enabled' => 'enabled'])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue($admin->fresh('readerProfile')->readerProfile->video_consulting_enabled);
    }

    public function test_admin_can_disable_video_consulting_for_themselves_again(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->readerProfile()->create([
            'initials' => 'AD', 'first_name' => 'Test', 'last_name' => 'Admin',
            'video_consulting_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->patch('/profile/video-consulting-enabled', ['video_consulting_enabled' => 'disabled'])
            ->assertRedirect(route('profile.edit'));

        $this->assertFalse($admin->fresh('readerProfile')->readerProfile->video_consulting_enabled);
    }

    public function test_editor_can_enable_video_consulting_for_themselves(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $editor->editorProfile()->create(['initials' => 'ED', 'first_name' => 'Test', 'last_name' => 'Editor']);

        $this->actingAs($editor)
            ->patch('/profile/video-consulting-enabled', ['video_consulting_enabled' => 'enabled'])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue($editor->fresh('readerProfile')->readerProfile->video_consulting_enabled);
    }

    public function test_reader_cannot_use_the_self_serve_enable_route(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $this->actingAs($reader)
            ->patch('/profile/video-consulting-enabled', ['video_consulting_enabled' => 'enabled'])
            ->assertNotFound();
    }

    public function test_admins_own_profile_page_shows_the_enable_control_before_opting_in(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/profile')
            ->assertOk()
            ->assertSee('Video Consulting');
    }
}
