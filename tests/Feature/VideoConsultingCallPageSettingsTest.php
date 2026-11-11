<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin-editable call page content (app/Http/Controllers/Api/VideoConsultingCallPageSettingsController.php),
 * gated by the shared-secret portal.secret middleware like every other theme-facing endpoint.
 */
class VideoConsultingCallPageSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(): array
    {
        config(['services.portal.webhook_secret' => 'test-secret']);

        return ['Authorization' => 'Bearer test-secret'];
    }

    public function test_index_returns_defaults_when_nothing_saved(): void
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/admin/call-page-settings');

        $response->assertOk()->assertJson(Setting::CALL_PAGE_DEFAULTS);
    }

    public function test_update_persists_and_merges_with_defaults(): void
    {
        $response = $this->withHeaders($this->authHeaders())->patchJson('/api/video-consulting/admin/call-page-settings', [
            'call_page_heading' => 'Your Call Is Coming Up',
            'call_page_logo_url' => 'https://example.com/logo.png',
        ]);

        $response->assertOk()
            ->assertJsonPath('call_page_heading', 'Your Call Is Coming Up')
            ->assertJsonPath('call_page_logo_url', 'https://example.com/logo.png')
            ->assertJsonPath('call_page_consent_text', Setting::CALL_PAGE_DEFAULTS['call_page_consent_text']);

        $this->assertDatabaseHas('settings', ['key' => 'call_page_heading', 'value' => 'Your Call Is Coming Up']);
    }

    public function test_update_rejects_invalid_logo_url(): void
    {
        $response = $this->withHeaders($this->authHeaders())->patchJson('/api/video-consulting/admin/call-page-settings', [
            'call_page_logo_url' => 'not-a-url',
        ]);

        $response->assertStatus(422);
    }

    public function test_update_allows_clearing_the_logo(): void
    {
        Setting::setValue('call_page_logo_url', 'https://example.com/logo.png');

        $response = $this->withHeaders($this->authHeaders())->patchJson('/api/video-consulting/admin/call-page-settings', [
            'call_page_logo_url' => '',
        ]);

        $response->assertOk()->assertJsonPath('call_page_logo_url', '');
    }

    public function test_rejects_request_without_valid_secret(): void
    {
        $response = $this->getJson('/api/video-consulting/admin/call-page-settings');

        $response->assertStatus(401);
    }
}
