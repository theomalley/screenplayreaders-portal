<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Permission;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Password::defaults(function () {
            return Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols();
        });

        $this->registerGates();
        $this->syncSessionLifetime();
    }

    /**
     * Laravel's own session cookie/GC lifetime must never be shorter than the
     * admin-configurable idle timeout (Setting::session_timeout_minutes, or a
     * per-admin override on users.session_timeout_minutes) — otherwise the
     * framework silently expires the session before CheckSessionTimeout's idle
     * check ever gets a chance to fire, making the configured value a no-op.
     */
    private function syncSessionLifetime(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        try {
            $globalTimeout = (int) \App\Models\Setting::getValue('session_timeout_minutes', 120);
            $maxOverride   = (int) (User::whereNotNull('session_timeout_minutes')->max('session_timeout_minutes') ?? 0);
            $required      = max($globalTimeout, $maxOverride, (int) config('session.lifetime'));

            config(['session.lifetime' => $required]);
        } catch (\Throwable $e) {
            // DB not reachable yet (e.g. fresh install before migrations run) — leave the
            // env-configured session.lifetime fallback in place.
        }
    }

    /**
     * Named Gate abilities for admin-only tool pages/actions that aren't tied to a
     * specific Eloquent model instance (so a Policy class isn't the right shape — see
     * app/Policies for genuine per-instance authorization like AssignmentPolicy).
     * Being consolidated here, resource by resource, replacing ad-hoc
     * abort_unless(auth()->user()->isAdmin()) calls scattered across controllers.
     */
    private function registerGates(): void
    {
        Gate::define('manage-profile-approvals', fn (User $user) => $user->isAdmin());
        Gate::define('manage-test-data', fn (User $user) => $user->isAdmin());
        Gate::define('manage-manual', fn (User $user) => $user->isAdmin());
        Gate::define('manage-filenames', fn (User $user) => $user->isAdmin());
        Gate::define('view-helpscout-webhook-logs', fn (User $user) => $user->isAdmin());
        Gate::define('manage-payout-schedule', fn (User $user) => $user->isAdmin());
        Gate::define('manage-permissions', fn (User $user) => $user->isAdmin());
        Gate::define('view-revenue', fn (User $user) => $user->isAdmin());
        Gate::define('view-payroll', fn (User $user) => $user->isAdmin());
        Gate::define('view-statistics', fn (User $user) => $user->isAdmin());

        // EditorPayController actions on OrderRevenue/EditorPayAdjustment — the editor-
        // targeted actions in the same controller live on UserPolicy (editorPay* methods)
        // since they authorize against a User instance; these two don't.
        Gate::define('editor-pay.manage-commission', fn (User $user) => $user->isAdmin());
        Gate::define('editor-pay.delete-adjustment', fn (User $user) => $user->isAdmin());

        // SettingController: ~39 actions, but only two distinct rules — kept as two Gate
        // abilities rather than 39 near-duplicate lines.
        Gate::define('manage-settings', fn (User $user) => $user->canManageAssignments());
        Gate::define('manage-settings-admin-only', fn (User $user) => $user->isAdmin());

        // WooOrderController — WooCommerce orders aren't a local Eloquent model (fetched
        // live via the WC REST API), so this is a Gate ability, not a Policy.
        Gate::define('manage-woo-orders', fn (User $user) => $user->isAdminOrEditor());

        // BudgetAdminController: rate-table config pages, not tied to an Eloquent instance.
        Gate::define('view-budget-admin', fn (User $user) => Permission::check('budget.admin', $user));
        Gate::define('edit-budget-admin', fn (User $user) => Permission::check('budget.admin.edit', $user));

        // QuickLoginController admin actions (not the public token-consumption route).
        Gate::define('manage-quick-login', fn (User $user) => $user->isAdmin());

        // UrlBuilderController — a tool page, not tied to an Eloquent instance.
        Gate::define('use-url-builder', fn (User $user) => $user->isAdminOrEditor());

        // Marketing\PartnerSiteController::updateFormSettings — site-wide form
        // defaults, not tied to a single PartnerSite instance (see PartnerSitePolicy
        // for the per-instance actions on that same controller).
        Gate::define('manage-partner-form-settings', fn (User $user) => $user->isAdmin());
    }
}
