<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin/editor self-serve toggle for whether this person can be counted as one of a
 * customer's requested readers when booking a video call with them — see
 * ProfileController::updateReaderRequestEligible(). A reader is always eligible and doesn't
 * get this control; neither admin nor editor writes coverage automatically, so both default
 * to off and opt in here.
 */
class ProfileReaderRequestEligibleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_opt_in_for_themselves(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->patch('/profile/reader-request-eligible', ['video_consulting_reader_request_eligible' => '1'])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue($admin->fresh('readerProfile')->readerProfile->video_consulting_reader_request_eligible);
    }

    public function test_admin_can_opt_out_again(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->readerProfile()->create([
            'initials' => 'AD', 'first_name' => 'Test', 'last_name' => 'Admin',
            'video_consulting_reader_request_eligible' => true,
        ]);

        $this->actingAs($admin)
            ->patch('/profile/reader-request-eligible', ['video_consulting_reader_request_eligible' => '0'])
            ->assertRedirect(route('profile.edit'));

        $this->assertFalse($admin->fresh('readerProfile')->readerProfile->video_consulting_reader_request_eligible);
    }

    public function test_editor_can_opt_in_for_themselves(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $editor->editorProfile()->create(['initials' => 'ED', 'first_name' => 'Test', 'last_name' => 'Editor']);

        $this->actingAs($editor)
            ->patch('/profile/reader-request-eligible', ['video_consulting_reader_request_eligible' => '1'])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue($editor->fresh('readerProfile')->readerProfile->video_consulting_reader_request_eligible);
    }

    public function test_reader_cannot_use_this_route(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $this->actingAs($reader)
            ->patch('/profile/reader-request-eligible', ['video_consulting_reader_request_eligible' => '1'])
            ->assertNotFound();
    }

    public function test_admins_own_profile_page_shows_the_toggle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/profile')
            ->assertOk()
            ->assertSee('count me as one of their requested readers');
    }

    public function test_editors_own_profile_page_shows_the_toggle(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $editor->editorProfile()->create(['initials' => 'ED', 'first_name' => 'Test', 'last_name' => 'Editor']);

        $this->actingAs($editor)->get('/profile')
            ->assertOk()
            ->assertSee('count me as one of their requested readers');
    }
}
