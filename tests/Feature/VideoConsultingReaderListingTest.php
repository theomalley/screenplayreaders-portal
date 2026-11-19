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

    public function test_reader_can_set_and_clear_note_and_call_mode(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $this->actingAs($reader)
            ->patch('/profile/video-consulting-listing', [
                'video_consulting_note' => 'Back Monday!',
                'video_consulting_call_mode' => 'audio',
            ])
            ->assertRedirect(route('profile.edit'));

        $profile = $reader->fresh('readerProfile')->readerProfile;
        $this->assertSame('Back Monday!', $profile->video_consulting_note);
        $this->assertSame('audio', $profile->video_consulting_call_mode);

        $this->actingAs($reader)
            ->patch('/profile/video-consulting-listing', [
                'video_consulting_note' => '   ',
                'video_consulting_call_mode' => 'video',
            ])
            ->assertRedirect(route('profile.edit'));

        $profile = $reader->fresh('readerProfile')->readerProfile;
        $this->assertNull($profile->video_consulting_note);
        $this->assertSame('video', $profile->video_consulting_call_mode);
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
}
