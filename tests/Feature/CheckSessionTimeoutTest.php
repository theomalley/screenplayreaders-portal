<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the CheckSessionTimeout fix (2026-11-22): a timed-out
 * admin was being auto-restored as whichever admin is configured for quick-login,
 * not necessarily themselves.
 */
class CheckSessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_different_admins_timeout_is_not_swapped_for_the_quick_login_admin(): void
    {
        $quickLoginAdmin = User::factory()->create(['role' => 'admin']);
        $otherAdmin      = User::factory()->create(['role' => 'admin']);

        Setting::setValue('admin_quick_login_user_id', (string) $quickLoginAdmin->id);
        Setting::setValue('session_timeout_minutes', 10);

        $this->actingAs($otherAdmin)
            ->withSession(['last_activity_at' => now()->subMinutes(30)->timestamp])
            ->get('/assignments')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_the_configured_quick_login_admins_own_timeout_still_restores_them(): void
    {
        $quickLoginAdmin = User::factory()->create(['role' => 'admin']);

        Setting::setValue('admin_quick_login_user_id', (string) $quickLoginAdmin->id);
        Setting::setValue('session_timeout_minutes', 10);

        $this->actingAs($quickLoginAdmin)
            ->withSession(['last_activity_at' => now()->subMinutes(30)->timestamp])
            ->get('/assignments')
            ->assertRedirect(route('assignments.index'));

        $this->assertAuthenticatedAs($quickLoginAdmin);
    }
}
