<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BudgetAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string}> budget.admin (view) routes */
    private function viewRoutes(): array
    {
        return [
            'index'          => ['GET', '/budget-admin'],
            'crew-rates'     => ['GET', '/budget-admin/crew-rates'],
            'fringes'        => ['GET', '/budget-admin/fringes'],
            'states'         => ['GET', '/budget-admin/states'],
            'allocations'    => ['GET', '/budget-admin/allocations'],
            'guild-mappings' => ['GET', '/budget-admin/guild-mappings'],
            'test-form'      => ['GET', '/budget-admin/test'],
        ];
    }

    /** @return array<string, array{0: string, 1: string}> budget.admin.edit (write) routes */
    private function editRoutes(): array
    {
        return [
            'crew-rates.update'     => ['PATCH', '/budget-admin/crew-rates'],
            'fringes.update'        => ['PATCH', '/budget-admin/fringes'],
            'states.update'         => ['PATCH', '/budget-admin/states'],
            'allocations.update'    => ['PATCH', '/budget-admin/allocations'],
            'guild-mappings.update' => ['PATCH', '/budget-admin/guild-mappings'],
        ];
    }

    private function hit(User $user, string $method, string $uri): TestResponse
    {
        return $this->actingAs($user)->call($method, $uri);
    }

    public function test_reader_is_forbidden_from_every_budget_admin_route(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);

        foreach (array_merge($this->viewRoutes(), $this->editRoutes()) as $name => [$method, $uri]) {
            $this->assertSame(403, $this->hit($reader, $method, $uri)->getStatusCode(), "reader should be forbidden from $name");
        }
    }

    public function test_admin_is_admitted_to_every_budget_admin_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (array_merge($this->viewRoutes(), $this->editRoutes()) as $name => [$method, $uri]) {
            $status = $this->hit($admin, $method, $uri)->getStatusCode();
            $this->assertNotEquals(403, $status, "admin should not be forbidden from $name (got $status)");
        }
    }

    public function test_editor_is_forbidden_from_every_budget_admin_route_by_default(): void
    {
        // budget.admin/budget.admin.edit both default to admin-only (Permission::DEFAULTS) —
        // an admin could grant editors view access via Tools > Permissions, but that's a
        // runtime DB setting, not something this authorization-boundary test toggles.
        $editor = User::factory()->create(['role' => 'editor']);

        foreach (array_merge($this->viewRoutes(), $this->editRoutes()) as $name => [$method, $uri]) {
            $this->assertSame(403, $this->hit($editor, $method, $uri)->getStatusCode(), "editor should be forbidden from $name by default");
        }
    }

    public function test_admin_can_run_test_calculation_and_deliver_and_batch(): void
    {
        Queue::fake();
        \App\Models\Budget\StateRate::create([
            'state_name'    => 'California',
            'sui_rate'      => 0.034,
            'sui_ceiling'   => 7000,
            'minimum_wage'  => 16.00,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/budget-admin/test', ['budget' => 50000])->assertRedirect();
        $this->actingAs($admin)->post('/budget-admin/test/deliver', ['budget' => 50000, 'test_email' => 'a@example.com'])->assertRedirect();
        $this->actingAs($admin)->post('/budget-admin/test/batch', ['batch_count' => 1])->assertRedirect();
    }

    public function test_editor_cannot_run_test_calculation_deliver_or_batch(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->post('/budget-admin/test', ['budget' => 50000])->assertForbidden();
        $this->actingAs($editor)->post('/budget-admin/test/deliver', ['budget' => 50000, 'test_email' => 'a@example.com'])->assertForbidden();
        $this->actingAs($editor)->post('/budget-admin/test/batch', ['batch_count' => 1])->assertForbidden();
    }
}
