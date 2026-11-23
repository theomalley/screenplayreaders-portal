<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Self-serve toggle (every role: reader, admin, editor) for whether this person can be
 * counted as one of a customer's requested readers when booking a video call with them —
 * see ProfileController::updateReaderRequestEligible() and
 * VideoConsultingAccess::eligibleForReaderRequestWaiver(). Defaults to true for everyone
 * until explicitly turned off.
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

    public function test_reader_can_opt_out_for_themselves(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $this->actingAs($reader)
            ->patch('/profile/reader-request-eligible', ['video_consulting_reader_request_eligible' => '0'])
            ->assertRedirect(route('profile.edit'));

        $this->assertFalse($reader->fresh('readerProfile')->readerProfile->video_consulting_reader_request_eligible);
    }

    public function test_admins_own_profile_page_shows_the_toggle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/profile')
            ->assertOk()
            ->assertSee('count you as one of their requested readers');
    }

    public function test_editors_own_profile_page_shows_the_toggle(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $editor->editorProfile()->create(['initials' => 'ED', 'first_name' => 'Test', 'last_name' => 'Editor']);

        $this->actingAs($editor)->get('/profile')
            ->assertOk()
            ->assertSee('count you as one of their requested readers');
    }

    public function test_readers_own_profile_page_shows_the_toggle_when_they_offer_video_consulting(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);
        $duration = \App\Models\VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        \App\Models\ReaderVideoConsultingRate::create(['reader_id' => $reader->id, 'duration_id' => $duration->id, 'rate' => 50]);

        $this->actingAs($reader)->get('/profile')
            ->assertOk()
            ->assertSee('count you as one of their requested readers');
    }

    public function test_a_reader_who_has_never_touched_the_toggle_is_eligible_by_default(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $this->assertTrue(\App\Support\VideoConsultingAccess::eligibleForReaderRequestWaiver($reader));
    }

    public function test_an_admin_or_editor_who_has_never_touched_the_toggle_is_eligible_by_default(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);

        $this->assertTrue(\App\Support\VideoConsultingAccess::eligibleForReaderRequestWaiver($admin));
        $this->assertTrue(\App\Support\VideoConsultingAccess::eligibleForReaderRequestWaiver($editor));
    }
}
