<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Covers the simple, single-purpose admin-tool controllers that have no natural
 * Eloquent model to authorize against (Manual, Filenames, HelpScoutWebhookLog,
 * PayoutSchedule, Permissions, Revenue, Payroll, Statistics) — every action in
 * each of these is uniformly admin-only. Asserts the authorization boundary
 * (403 for non-admins, not-403 for admins) rather than full business-logic
 * success, since some of these write actions need request bodies unrelated to
 * what's being tested here.
 */
class AdminOnlyToolControllersTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string}> method => uri */
    private function routes(): array
    {
        return [
            'manual.update'       => ['PATCH', '/manual'],
            'filenames.index'     => ['GET', '/admin/filenames'],
            'filenames.update'    => ['PATCH', '/admin/filenames'],
            'helpscout-logs'      => ['GET', '/admin/helpscout-webhook-logs'],
            'payroll-sched.update'=> ['PATCH', '/payroll/schedule'],
            'payroll-sched.override' => ['PATCH', '/payroll/schedule/override'],
            'permissions.index'   => ['GET', '/admin/permissions'],
            'permissions.update'  => ['POST', '/admin/permissions'],
            'revenue.index'       => ['GET', '/revenue'],
            'revenue.byCustomer'  => ['GET', '/revenue/by-customer'],
            'payroll.index'       => ['GET', '/payroll'],
            'payroll.export1099'  => ['GET', '/payroll/export-1099'],
            'statistics.index'    => ['GET', '/statistics'],
        ];
    }

    private function hit(User $user, string $method, string $uri): TestResponse
    {
        return $this->actingAs($user)->call($method, $uri);
    }

    public function test_every_admin_only_tool_rejects_editors_and_readers(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $reader = User::factory()->create(['role' => 'reader']);

        foreach ($this->routes() as $name => [$method, $uri]) {
            $this->assertSame(403, $this->hit($editor, $method, $uri)->getStatusCode(), "editor should be forbidden from $name");
            $this->assertSame(403, $this->hit($reader, $method, $uri)->getStatusCode(), "reader should be forbidden from $name");
        }
    }

    public function test_every_admin_only_tool_admits_admins(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ($this->routes() as $name => [$method, $uri]) {
            $status = $this->hit($admin, $method, $uri)->getStatusCode();
            $this->assertNotEquals(403, $status, "admin should not be forbidden from $name (got $status)");
        }
    }
}
