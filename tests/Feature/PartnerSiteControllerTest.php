<?php

namespace Tests\Feature;

use App\Models\PartnerSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PartnerSiteControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeSite(): PartnerSite
    {
        return PartnerSite::create([
            'name' => 'Test Partner',
            'url'  => 'https://example.com',
        ]);
    }

    public function test_admin_can_use_every_partner_site_action(): void
    {
        Http::fake(fn () => Http::response('<a href="https://screenplayreaders.com">link</a>', 200));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/marketing/partner-sites')->assertOk();
        $this->actingAs($admin)->post('/marketing/partner-sites', ['name' => 'New', 'url' => 'https://example.org'])->assertRedirect();

        $site = $this->makeSite();
        $this->actingAs($admin)->patch("/marketing/partner-sites/{$site->id}", ['name' => 'Updated', 'url' => $site->url])->assertRedirect();
        $this->actingAs($admin)->post("/marketing/partner-sites/{$site->id}/check-now")->assertOk();
        $this->actingAs($admin)->post("/marketing/partner-sites/{$site->id}/toggle-active")->assertOk();
        $this->actingAs($admin)->get("/marketing/partner-sites/{$site->id}/history")->assertOk();
        $this->actingAs($admin)->patch('/marketing/partner-sites/form-settings', [])->assertRedirect();

        $site2 = $this->makeSite();
        $this->actingAs($admin)->delete("/marketing/partner-sites/{$site2->id}")->assertRedirect();
        $this->assertDatabaseMissing('partner_sites', ['id' => $site2->id]);
    }

    public function test_editor_cannot_use_any_partner_site_action(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $site   = $this->makeSite();

        $this->actingAs($editor)->get('/marketing/partner-sites')->assertForbidden();
        $this->actingAs($editor)->post('/marketing/partner-sites', ['name' => 'New', 'url' => 'https://example.org'])->assertForbidden();
        $this->actingAs($editor)->patch("/marketing/partner-sites/{$site->id}", [])->assertForbidden();
        $this->actingAs($editor)->post("/marketing/partner-sites/{$site->id}/check-now")->assertForbidden();
        $this->actingAs($editor)->post("/marketing/partner-sites/{$site->id}/toggle-active")->assertForbidden();
        $this->actingAs($editor)->get("/marketing/partner-sites/{$site->id}/history")->assertForbidden();
        $this->actingAs($editor)->patch('/marketing/partner-sites/form-settings', [])->assertForbidden();
        $this->actingAs($editor)->delete("/marketing/partner-sites/{$site->id}")->assertForbidden();
    }
}
