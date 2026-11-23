<?php

namespace Tests\Feature;

use App\Models\ReaderAvailabilityRule;
use App\Models\ReaderVideoConsultingRate;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reader-authored video-consulting listing note + call mode
 * (ProfileController::updateVideoConsultingListing()).
 */
class VideoConsultingReaderListingTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(): array
    {
        config(['services.portal.webhook_secret' => 'test-secret']);

        return ['Authorization' => 'Bearer test-secret', 'Accept' => 'application/json'];
    }

    private function makeBookableReader(array $profileOverrides = []): array
    {
        $reader = User::factory()->create(['role' => 'reader', 'name' => 'Jamie Reader']);
        $reader->readerProfile()->create(array_merge([
            'initials' => 'JR', 'first_name' => 'Jamie', 'last_name' => 'Reader',
        ], $profileOverrides));
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        $rate = ReaderVideoConsultingRate::create(['reader_id' => $reader->id, 'duration_id' => $duration->id, 'rate' => 50]);

        $day = Carbon::now()->addDays(14)->startOfDay();
        ReaderAvailabilityRule::create([
            'reader_id' => $reader->id, 'day_of_week' => $day->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '11:00', 'enabled' => true,
        ]);

        return [$reader, $duration, $rate, $day];
    }

    // ---- Self-serve note + call mode -----------------------------------------------------

    public function test_reader_submission_holds_text_fields_pending_but_saves_call_mode_live(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $this->actingAs($reader)
            ->patch('/profile/video-consulting-listing', [
                'video_consulting_note' => 'Back Monday!',
                'video_consulting_call_mode' => 'audio',
                'video_consulting_blurb_header' => 'Animation specialist',
                'video_consulting_blurb' => 'I love animated features.',
            ])
            ->assertRedirect(route('profile.edit'));

        $profile = $reader->fresh('readerProfile')->readerProfile;
        // Text fields don't go live yet — held for admin approval.
        $this->assertNull($profile->video_consulting_note);
        $this->assertNull($profile->video_consulting_blurb_header);
        $this->assertNull($profile->video_consulting_blurb);
        $this->assertSame([
            'video_consulting_note' => 'Back Monday!',
            'video_consulting_blurb_header' => 'Animation specialist',
            'video_consulting_blurb' => 'I love animated features.',
        ], $profile->video_consulting_listing_pending);
        // call_mode isn't free text — saves live immediately regardless of role.
        $this->assertSame('audio', $profile->video_consulting_call_mode);
    }

    public function test_admin_approving_pending_listing_promotes_it_live_and_clears_pending(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $profile = $reader->readerProfile()->create([
            'initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader',
            'video_consulting_listing_pending' => [
                'video_consulting_note' => 'Back Monday!',
                'video_consulting_blurb_header' => 'Animation specialist',
                'video_consulting_blurb' => 'I love animated features.',
            ],
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.approvals.video-consulting-listing.approve', $reader))
            ->assertOk();

        $profile->refresh();
        $this->assertSame('Back Monday!', $profile->video_consulting_note);
        $this->assertSame('Animation specialist', $profile->video_consulting_blurb_header);
        $this->assertSame('I love animated features.', $profile->video_consulting_blurb);
        $this->assertNull($profile->video_consulting_listing_pending);
    }

    public function test_admin_rejecting_pending_listing_clears_pending_and_stores_rejection_note(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $profile = $reader->readerProfile()->create([
            'initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader',
            'video_consulting_listing_pending' => ['video_consulting_note' => 'Back Monday!'],
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.approvals.video-consulting-listing.reject', $reader), ['note' => 'Please shorten this.'])
            ->assertOk();

        $profile->refresh();
        $this->assertNull($profile->video_consulting_listing_pending);
        $this->assertNull($profile->video_consulting_note);
        $this->assertSame('Please shorten this.', $profile->video_consulting_listing_rejection_note);
    }

    public function test_listing_header_and_note_are_capped_at_30_words_and_blurb_at_70(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $thirtyOneWords = implode(' ', array_fill(0, 31, 'word'));
        $seventyOneWords = implode(' ', array_fill(0, 71, 'word'));

        $this->actingAs($reader)
            ->patch('/profile/video-consulting-listing', [
                'video_consulting_call_mode' => 'video',
                'video_consulting_blurb_header' => $thirtyOneWords,
                'video_consulting_note' => $thirtyOneWords,
                'video_consulting_blurb' => $seventyOneWords,
            ])
            ->assertSessionHasErrors([
                'video_consulting_blurb_header',
                'video_consulting_note',
                'video_consulting_blurb',
            ]);

        $thirtyWords = implode(' ', array_fill(0, 30, 'word'));
        $seventyWords = implode(' ', array_fill(0, 70, 'word'));

        $this->actingAs($reader)
            ->patch('/profile/video-consulting-listing', [
                'video_consulting_call_mode' => 'video',
                'video_consulting_blurb_header' => $thirtyWords,
                'video_consulting_note' => $thirtyWords,
                'video_consulting_blurb' => $seventyWords,
            ])
            ->assertSessionDoesntHaveErrors([
                'video_consulting_blurb_header',
                'video_consulting_note',
                'video_consulting_blurb',
            ]);
    }

    public function test_admin_can_set_note_and_call_mode_for_themselves(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->patch('/profile/video-consulting-listing', [
                'video_consulting_note' => 'On vacation, back the 1st',
                'video_consulting_call_mode' => 'audio',
            ])
            ->assertRedirect(route('profile.edit'));

        $profile = $admin->fresh('readerProfile')->readerProfile;
        $this->assertSame('On vacation, back the 1st', $profile->video_consulting_note);
        $this->assertSame('audio', $profile->video_consulting_call_mode);
    }

    public function test_call_mode_is_required_and_must_be_video_or_audio(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $this->actingAs($reader)
            ->patch('/profile/video-consulting-listing', ['video_consulting_call_mode' => 'surround-sound'])
            ->assertSessionHasErrors('video_consulting_call_mode');
    }

    // ---- readers() API ---------------------------------------------------------------------

    public function test_readers_endpoint_includes_note_and_call_mode_for_an_enabled_reader_with_no_open_slots(): void
    {
        $reader = User::factory()->create(['role' => 'reader', 'name' => 'Jamie Reader']);
        $reader->readerProfile()->create([
            'initials' => 'JR', 'first_name' => 'Jamie', 'last_name' => 'Reader',
            'video_consulting_note' => 'Back Monday!', 'video_consulting_call_mode' => 'audio',
        ]);
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        ReaderVideoConsultingRate::create(['reader_id' => $reader->id, 'duration_id' => $duration->id, 'rate' => 50]);
        // Deliberately no ReaderAvailabilityRule — no open slots right now, but still enabled
        // and still has a configured rate, so they must still appear (unchanged behavior).

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/readers');

        $response->assertOk();
        $payload = $response->json();
        $this->assertCount(1, $payload);
        $this->assertSame('Back Monday!', $payload[0]['note']);
        $this->assertSame('audio', $payload[0]['call_mode']);
    }

    public function test_readers_endpoint_excludes_a_fully_disabled_reader_even_with_a_note_set(): void
    {
        [$reader] = $this->makeBookableReader([
            'video_consulting_enabled' => false,
            'video_consulting_note' => 'Back Monday!',
        ]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/readers');

        $response->assertOk();
        $this->assertCount(0, $response->json());
    }

    // ---- Admin direct-edit of another person's listing (ReaderProfileController::update() /
    //      EditorProfileController::update()) — unlike the reader's own self-serve form, this
    //      goes straight live with no pending-approval step. ------------------------------------

    public function test_admin_can_directly_edit_a_readers_listing_fields_live(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $reader = $this->makeReader();

        $this->actingAs($admin)
            ->patch("/readers/{$reader->id}", $this->readerUpdatePayload($reader, [
                'video_consulting_blurb_header' => 'Animation specialist',
                'video_consulting_blurb' => 'I love animated features.',
                'video_consulting_note' => 'Back Monday!',
                'video_consulting_call_mode' => 'audio',
            ]))
            ->assertSessionDoesntHaveErrors();

        $profile = $reader->fresh('readerProfile')->readerProfile;
        $this->assertSame('Animation specialist', $profile->video_consulting_blurb_header);
        $this->assertSame('I love animated features.', $profile->video_consulting_blurb);
        $this->assertSame('Back Monday!', $profile->video_consulting_note);
        $this->assertSame('audio', $profile->video_consulting_call_mode);
    }

    public function test_admin_editing_a_readers_listing_clears_a_pending_submission(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $reader = $this->makeReader();
        $reader->readerProfile->update([
            'video_consulting_listing_pending' => ['video_consulting_note' => 'Reader submitted this'],
            'video_consulting_listing_rejection_note' => 'Old rejection note',
        ]);

        $this->actingAs($admin)
            ->patch("/readers/{$reader->id}", $this->readerUpdatePayload($reader, [
                'video_consulting_note' => 'Admin override',
            ]))
            ->assertSessionDoesntHaveErrors();

        $profile = $reader->fresh('readerProfile')->readerProfile;
        $this->assertSame('Admin override', $profile->video_consulting_note);
        $this->assertNull($profile->video_consulting_listing_pending);
        $this->assertNull($profile->video_consulting_listing_rejection_note);
    }

    public function test_admin_editing_a_readers_listing_is_capped_at_word_limits(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $reader = $this->makeReader();

        $this->actingAs($admin)
            ->patch("/readers/{$reader->id}", $this->readerUpdatePayload($reader, [
                'video_consulting_blurb_header' => implode(' ', array_fill(0, 31, 'word')),
            ]))
            ->assertSessionHasErrors('video_consulting_blurb_header');
    }

    public function test_omitting_listing_fields_from_an_unrelated_admin_update_leaves_them_untouched(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $reader = $this->makeReader();
        $reader->readerProfile->update(['video_consulting_note' => 'Existing note']);

        $this->actingAs($admin)
            ->patch("/readers/{$reader->id}", $this->readerUpdatePayload($reader))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('Existing note', $reader->fresh('readerProfile')->readerProfile->video_consulting_note);
    }

    public function test_admin_can_directly_edit_an_editors_listing_fields_live_via_the_readerprofile_shell(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);
        $editor->editorProfile()->create(['initials' => 'ED', 'first_name' => 'Ed', 'last_name' => 'Itor']);

        $this->actingAs($admin)
            ->patch("/admin/editors/{$editor->id}", $this->editorUpdatePayload($editor, [
                'video_consulting_blurb_header' => 'Thriller specialist',
                'video_consulting_blurb' => 'Fast turnaround, sharp notes.',
                'video_consulting_note' => 'Booked through Friday',
                'video_consulting_call_mode' => 'audio',
            ]))
            ->assertSessionDoesntHaveErrors();

        $shell = $editor->fresh('readerProfile')->readerProfile;
        $this->assertSame('Thriller specialist', $shell->video_consulting_blurb_header);
        $this->assertSame('Fast turnaround, sharp notes.', $shell->video_consulting_blurb);
        $this->assertSame('Booked through Friday', $shell->video_consulting_note);
        $this->assertSame('audio', $shell->video_consulting_call_mode);
    }

    private function makeReader(): User
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        return $reader;
    }

    private function readerUpdatePayload(User $reader, array $overrides = []): array
    {
        return array_merge([
            'initials' => 'TR',
            'first_name' => 'Test',
            'last_name' => 'Reader',
            'email' => $reader->email,
            'availability' => 'available',
        ], $overrides);
    }

    private function editorUpdatePayload(User $editor, array $overrides = []): array
    {
        return array_merge([
            'initials' => 'ED',
            'first_name' => 'Ed',
            'last_name' => 'Itor',
            'email' => $editor->email,
            'availability' => 'available',
        ], $overrides);
    }
}
