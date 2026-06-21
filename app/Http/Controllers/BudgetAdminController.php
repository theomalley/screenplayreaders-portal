<?php

// v1.0 — 2026-06-21 | Initial: admin UI for budget crew rates, fringes, state rates,
//                      department allocations, and guild tier mappings

namespace App\Http\Controllers;

use App\Models\Budget\BudgetOrder;
use App\Models\Budget\CrewPosition;
use App\Models\Budget\DepartmentAllocation;
use App\Models\Budget\FringeRate;
use App\Models\Budget\GuildTierMapping;
use App\Models\Budget\RateTier;
use App\Models\Budget\StateRate;
use App\Services\Budget\BudgetCalculationService;
use App\Support\Permission;
use Illuminate\Http\Request;

class BudgetAdminController extends Controller
{
    public function index()
    {
        abort_unless(Permission::check('budget.admin'), 403);

        return view('budget-admin.index');
    }

    // ── Crew Rates ──

    public function crewRates()
    {
        abort_unless(Permission::check('budget.admin'), 403);

        $positions = CrewPosition::with('rateTiers')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('department');

        $canEdit = Permission::check('budget.admin.edit');

        return view('budget-admin.crew-rates', compact('positions', 'canEdit'));
    }

    public function updateCrewRates(Request $request)
    {
        abort_unless(Permission::check('budget.admin.edit'), 403);

        $tiers = $request->input('tiers', []);

        foreach ($tiers as $tierId => $value) {
            RateTier::where('id', (int) $tierId)->update([
                'rate_value' => max(0, (float) $value),
            ]);
        }

        return back()->with('success', 'Crew rates saved.');
    }

    // ── Fringes ──

    public function fringes()
    {
        abort_unless(Permission::check('budget.admin'), 403);

        $fringes = FringeRate::orderBy('sort_order')->get();
        $canEdit = Permission::check('budget.admin.edit');

        return view('budget-admin.fringes', compact('fringes', 'canEdit'));
    }

    public function updateFringes(Request $request)
    {
        abort_unless(Permission::check('budget.admin.edit'), 403);

        $data = $request->input('fringes', []);

        foreach ($data as $id => $fields) {
            FringeRate::where('id', (int) $id)->update([
                'rate'         => max(0, (float) ($fields['rate'] ?? 0)) / 100,
                'ceiling'      => isset($fields['ceiling']) && $fields['ceiling'] !== '' ? max(0, (float) $fields['ceiling']) : null,
                'hourly_addon' => isset($fields['hourly_addon']) && $fields['hourly_addon'] !== '' ? max(0, (float) $fields['hourly_addon']) : null,
            ]);
        }

        return back()->with('success', 'Fringe rates saved.');
    }

    // ── States ──

    public function states()
    {
        abort_unless(Permission::check('budget.admin'), 403);

        $states = StateRate::orderBy('state_name')->get();
        $canEdit = Permission::check('budget.admin.edit');

        return view('budget-admin.states', compact('states', 'canEdit'));
    }

    public function updateStates(Request $request)
    {
        abort_unless(Permission::check('budget.admin.edit'), 403);

        $data = $request->input('states', []);

        foreach ($data as $id => $fields) {
            StateRate::where('id', (int) $id)->update([
                'sui_rate'     => max(0, (float) ($fields['sui_rate'] ?? 0)) / 100,
                'sui_ceiling'  => max(0, (float) ($fields['sui_ceiling'] ?? 0)),
                'minimum_wage' => max(0, (float) ($fields['minimum_wage'] ?? 0)),
            ]);
        }

        return back()->with('success', 'State rates saved.');
    }

    // ── Department Allocations ──

    public function allocations()
    {
        abort_unless(Permission::check('budget.admin'), 403);

        $allocations = DepartmentAllocation::orderBy('department_slug')
            ->orderBy('budget_class')
            ->get()
            ->groupBy('department_slug');

        $canEdit = Permission::check('budget.admin.edit');

        return view('budget-admin.allocations', compact('allocations', 'canEdit'));
    }

    public function updateAllocations(Request $request)
    {
        abort_unless(Permission::check('budget.admin.edit'), 403);

        $data = $request->input('allocs', []);

        foreach ($data as $id => $value) {
            DepartmentAllocation::where('id', (int) $id)->update([
                'percentage' => max(0, (float) $value) / 100,
            ]);
        }

        return back()->with('success', 'Department allocations saved.');
    }

    // ── Guild Tier Mappings ──

    public function guildMappings()
    {
        abort_unless(Permission::check('budget.admin'), 403);

        $mappings = GuildTierMapping::orderBy('guild')
            ->orderBy('budget_class')
            ->get()
            ->groupBy('guild');

        $canEdit = Permission::check('budget.admin.edit');

        return view('budget-admin.guild-mappings', compact('mappings', 'canEdit'));
    }

    public function updateGuildMappings(Request $request)
    {
        abort_unless(Permission::check('budget.admin.edit'), 403);

        $data = $request->input('mappings', []);

        foreach ($data as $id => $tierCode) {
            GuildTierMapping::where('id', (int) $id)->update([
                'tier_code' => max(0, (int) $tierCode),
            ]);
        }

        return back()->with('success', 'Guild tier mappings saved.');
    }

    // ── Test Calculator ──

    public function testForm(Request $request)
    {
        abort_unless(Permission::check('budget.admin'), 403);

        $states = StateRate::orderBy('state_name')->pluck('state_name')->toArray();
        $payload = session('test_payload');
        $elapsed = session('test_elapsed');
        $input = session('test_input', []);

        return view('budget-admin.test', compact('states', 'payload', 'elapsed', 'input'));
    }

    public function testRun(Request $request)
    {
        abort_unless(Permission::check('budget.admin.edit'), 403);

        $validated = $request->validate([
            'budget'       => 'required|numeric|min:25000|max:250000000',
            'shootingstate' => 'nullable|string|max:50',
            'guilds'       => 'nullable|string',
            'cast_count'   => 'nullable|integer|min:0|max:25',
            'use_defaults' => 'nullable|boolean',
        ]);

        $budget = (float) $validated['budget'];
        $guilds = $validated['guilds'] ?? 'all';

        $input = [
            'budget'              => $budget,
            'shootingstate'       => $validated['shootingstate'] ?? 'California',
            'userusetimedefaults' => ($validated['use_defaults'] ?? true) ? '1' : '0',
            'usercastsize'        => (string) ($validated['cast_count'] ?? 4),
            'usercast'            => '0',
            'userstunts'          => '0',
            'usertravel'          => '0',
            'userspfx'            => '0',
            'usermufx'            => '0',
            'useranimals'         => '0',
            'uservfx'             => '0',
            'userweeksprep'       => '0',
            'userweeksshoot'      => '0',
            'userweekswrap'       => '0',
            'userweekspost'       => '0',
            'headertitle'         => 'Test Budget',
            'headernamefirst'     => 'Test',
            'headernamelast'      => 'User',
            'headerdirector'      => '',
            'headerdate'          => now()->format('m/d/Y'),
            'budgettype'          => 'Feature or Short Film',
            'projecttitle'        => 'Test Budget',
        ];

        // Guild flags
        if ($guilds === 'all') {
            $input['usersag'] = '1';
            $input['userwga'] = '1';
            $input['userdga'] = '1';
            $input['useriatse'] = '1';
            $input['userteamsters'] = '1';
        } elseif ($guilds === 'none') {
            $input['usersag'] = '0';
            $input['userwga'] = '0';
            $input['userdga'] = '0';
            $input['useriatse'] = '0';
            $input['userteamsters'] = '0';
        } else {
            $input['usersag'] = '1';
            $input['userwga'] = '0';
            $input['userdga'] = '0';
            $input['useriatse'] = '0';
            $input['userteamsters'] = '0';
        }

        // Cast member names
        $castCount = (int) ($validated['cast_count'] ?? 4);
        for ($i = 1; $i <= 25; $i++) {
            $input['cast' . str_pad($i, 2, '0', STR_PAD_LEFT)] = $i <= $castCount ? "Cast Member {$i}" : '';
        }

        $start = microtime(true);

        try {
            $service = new BudgetCalculationService();
            $payload = $service->calculate($input);
            $elapsed = round((microtime(true) - $start) * 1000);

            return redirect()->route('budget-admin.test')
                ->with('test_payload', $payload)
                ->with('test_elapsed', $elapsed)
                ->with('test_input', $input);
        } catch (\Throwable $e) {
            return redirect()->route('budget-admin.test')
                ->with('test_input', $input)
                ->withErrors(['calculation' => $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine()]);
        }
    }
}
