<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the admin-only management actions on QuickLoginController (saveLanding,
 * generate, revoke) — not the public token-consumption route (login), which is
 * intentionally unauthenticated by design.
 */
class QuickLoginAdminActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_landing_generate_and_revoke(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/settings/quick-login/landing', ['landing' => 'qc.index'])->assertRedirect();
        $this->actingAs($admin)->post('/settings/quick-login/generate')->assertRedirect();
        $this->actingAs($admin)->delete('/settings/quick-login')->assertRedirect();
    }

    public function test_editor_cannot_save_landing_generate_or_revoke(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->post('/settings/quick-login/landing', ['landing' => 'qc.index'])->assertForbidden();
        $this->actingAs($editor)->post('/settings/quick-login/generate')->assertForbidden();
        $this->actingAs($editor)->delete('/settings/quick-login')->assertForbidden();
    }
}
