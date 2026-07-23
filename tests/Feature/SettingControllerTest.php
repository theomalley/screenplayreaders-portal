<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * SettingController has ~39 actions but only two distinct authorization rules:
 * canManageAssignments() (admin or editor — 7 actions, including emailAllReaders
 * which spells it isAdminOrEditor(), an identical check) and isAdmin() (32 actions).
 * Asserts the authorization boundary, not full business-logic success — many of
 * these write actions need specific request bodies unrelated to what's tested here.
 */
class SettingControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string}> admin-or-editor routes */
    private function adminOrEditorRoutes(): array
    {
        return [
            'index'                  => ['GET', '/settings'],
            'assignments'            => ['GET', '/settings/assignments'],
            'emails'                 => ['GET', '/settings/emails'],
            'orders'                 => ['GET', '/settings/orders'],
            'capacity-override'      => ['PATCH', '/settings/capacity-override'],
            'theme'                  => ['PATCH', '/settings/theme'],
            'email-all-readers'      => ['POST', '/settings/email-all-readers'],
        ];
    }

    /** @return array<string, array{0: string, 1: string}> admin-only routes */
    private function adminOnlyRoutes(): array
    {
        return [
            'logo'                              => ['POST', '/settings/logo'],
            'login-logo'                         => ['POST', '/settings/login-logo'],
            'favicon'                            => ['POST', '/settings/favicon'],
            'session-timeout'                    => ['PATCH', '/settings/session-timeout'],
            'invoice'                            => ['PATCH', '/settings/invoice'],
            'age-thresholds'                     => ['PATCH', '/settings/age-thresholds'],
            'coverage-success (view)'            => ['GET', '/settings/coverage-success'],
            'coverage-success (update)'          => ['PATCH', '/settings/coverage-success'],
            'dev-autofill'                       => ['PATCH', '/settings/dev-autofill'],
            'watermark'                          => ['PATCH', '/settings/watermark'],
            'qc-saved-replies'                   => ['PATCH', '/settings/qc-saved-replies'],
            'email-notifications'                => ['PATCH', '/settings/email-notifications'],
            'timezone'                           => ['PATCH', '/settings/timezone'],
            'followup-html'                      => ['PATCH', '/settings/followup-html'],
            'followup-response-draft'            => ['PATCH', '/settings/followup-response-draft'],
            'followup-response-draft.test'       => ['POST', '/settings/followup-response-draft/test'],
            'completion-draft'                   => ['PATCH', '/settings/completion-draft'],
            'completion-draft.test'              => ['POST', '/settings/completion-draft/test'],
            'word-counts'                        => ['PATCH', '/settings/word-counts'],
            'blocked-reader-limits'              => ['PATCH', '/settings/blocked-reader-limits'],
            'notification-history-retention'     => ['PATCH', '/settings/notification-history-retention'],
            'portal-photo'                       => ['POST', '/settings/portal-photo'],
            'about-photo'                        => ['POST', '/settings/about-photo'],
            'pay-period'                          => ['PATCH', '/settings/pay-period'],
            'reset-last-seen-all'                => ['POST', '/settings/reset-last-seen-all'],
            'reset-last-seen-me'                 => ['POST', '/settings/reset-last-seen-me'],
            'default-editor'                     => ['PATCH', '/settings/default-editor'],
            'order-log-editor'                   => ['PATCH', '/settings/order-log-editor'],
            'discount-coupon'                    => ['PATCH', '/settings/discount-coupon'],
            'commission-products.add'            => ['POST', '/settings/commission-products/add'],
            'commission-products.remove'         => ['POST', '/settings/commission-products/remove'],
        ];
    }

    private function hit(User $user, string $method, string $uri): TestResponse
    {
        return $this->actingAs($user)->call($method, $uri);
    }

    public function test_reader_is_forbidden_from_every_setting_action(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);

        foreach (array_merge($this->adminOrEditorRoutes(), $this->adminOnlyRoutes()) as $name => [$method, $uri]) {
            $this->assertSame(403, $this->hit($reader, $method, $uri)->getStatusCode(), "reader should be forbidden from $name");
        }
    }

    public function test_admin_and_editor_are_admitted_to_admin_or_editor_routes(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);

        foreach ($this->adminOrEditorRoutes() as $name => [$method, $uri]) {
            $this->assertNotEquals(403, $this->hit($admin, $method, $uri)->getStatusCode(), "admin should not be forbidden from $name");
            $this->assertNotEquals(403, $this->hit($editor, $method, $uri)->getStatusCode(), "editor should not be forbidden from $name");
        }
    }

    public function test_editor_is_forbidden_from_admin_only_routes(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        foreach ($this->adminOnlyRoutes() as $name => [$method, $uri]) {
            $this->assertSame(403, $this->hit($editor, $method, $uri)->getStatusCode(), "editor should be forbidden from $name");
        }
    }

    public function test_admin_is_admitted_to_admin_only_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ($this->adminOnlyRoutes() as $name => [$method, $uri]) {
            $status = $this->hit($admin, $method, $uri)->getStatusCode();
            $this->assertNotEquals(403, $status, "admin should not be forbidden from $name (got $status)");
        }
    }
}
