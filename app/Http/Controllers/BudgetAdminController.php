<?php

// v1.1 — 2026-07-23 | Authorization moved to view-budget-admin/edit-budget-admin Gate
//                     abilities (AppServiceProvider), replacing inline abort_unless(...)
//                     calls. Covered by tests/Feature/BudgetAdminControllerTest.php.
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
use App\Jobs\GenerateBudgetFiles;
use App\Services\Budget\BudgetCalculationService;
use App\Support\Permission;
use Illuminate\Http\Request;

class BudgetAdminController extends Controller
{
    public function index()
    {
        $this->authorize('view-budget-admin');

        return view('budget-admin.index');
    }

    // ── Crew Rates ──

    public function crewRates()
    {
        $this->authorize('view-budget-admin');

        $positions = CrewPosition::with('rateTiers')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('department');

        $canEdit = Permission::check('budget.admin.edit');

        return view('budget-admin.crew-rates', compact('positions', 'canEdit'));
    }

    public function updateCrewRates(Request $request)
    {
        $this->authorize('edit-budget-admin');

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
        $this->authorize('view-budget-admin');

        $fringes = FringeRate::orderBy('sort_order')->get();
        $canEdit = Permission::check('budget.admin.edit');

        return view('budget-admin.fringes', compact('fringes', 'canEdit'));
    }

    public function updateFringes(Request $request)
    {
        $this->authorize('edit-budget-admin');

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
        $this->authorize('view-budget-admin');

        $states = StateRate::orderBy('state_name')->get();
        $canEdit = Permission::check('budget.admin.edit');

        return view('budget-admin.states', compact('states', 'canEdit'));
    }

    public function updateStates(Request $request)
    {
        $this->authorize('edit-budget-admin');

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
        $this->authorize('view-budget-admin');

        $allocations = DepartmentAllocation::orderBy('department_slug')
            ->orderBy('budget_class')
            ->get()
            ->groupBy('department_slug');

        $canEdit = Permission::check('budget.admin.edit');

        return view('budget-admin.allocations', compact('allocations', 'canEdit'));
    }

    public function updateAllocations(Request $request)
    {
        $this->authorize('edit-budget-admin');

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
        $this->authorize('view-budget-admin');

        $mappings = GuildTierMapping::orderBy('guild')
            ->orderBy('budget_class')
            ->get()
            ->groupBy('guild');

        $canEdit = Permission::check('budget.admin.edit');

        return view('budget-admin.guild-mappings', compact('mappings', 'canEdit'));
    }

    public function updateGuildMappings(Request $request)
    {
        $this->authorize('edit-budget-admin');

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
        $this->authorize('view-budget-admin');

        $states = StateRate::orderBy('state_name')->pluck('state_name')->toArray();
        $payload = session('test_payload');
        $elapsed = session('test_elapsed');
        $input = session('test_input', []);
        $delivery = session('test_delivery');
        $batchResults = session('batch_results');

        return view('budget-admin.test', compact('states', 'payload', 'elapsed', 'input', 'delivery', 'batchResults'));
    }

    public function testRun(Request $request)
    {
        $this->authorize('edit-budget-admin');

        $validated = $request->validate([
            'budget'       => 'required|numeric|min:25000|max:250000000',
            'shootingstate' => 'nullable|string|max:50',
            'guilds'       => 'nullable|string',
            'cast_count'   => 'nullable|integer|min:0|max:25',
            'use_defaults' => 'nullable|boolean',
            'usercast'     => 'nullable|numeric|min:0|max:10',
            'userstunts'   => 'nullable|numeric|min:0|max:10',
            'usertravel'   => 'nullable|numeric|min:0|max:10',
            'userspfx'     => 'nullable|numeric|min:0|max:10',
            'usermufx'     => 'nullable|numeric|min:0|max:10',
            'useranimals'  => 'nullable|numeric|min:0|max:10',
            'uservfx'      => 'nullable|numeric|min:0|max:10',
        ]);

        $budget = (float) $validated['budget'];
        $guilds = $validated['guilds'] ?? 'all';

        // Customization points: use form values if provided, else defaults for budget level
        $hasCustomPoints = $request->filled('usercast') || $request->filled('userstunts')
            || $request->filled('userspfx') || $request->filled('usermufx') || $request->filled('uservfx');

        $input = [
            'budget'              => $budget,
            'shootingstate'       => $validated['shootingstate'] ?? 'California',
            'userusetimedefaults' => ($validated['use_defaults'] ?? true) ? '1' : '0',
            'usercastsize'        => (string) ($validated['cast_count'] ?? 4),
            '_guilds'             => $guilds,
            'usercast'            => $hasCustomPoints ? (string) ($validated['usercast'] ?? '0') : ($budget < 500000 ? '8.5' : '4'),
            'userstunts'          => $hasCustomPoints ? (string) ($validated['userstunts'] ?? '0') : ($budget < 500000 ? '0.5' : '1'),
            'usertravel'          => (string) ($validated['usertravel'] ?? '0'),
            'userspfx'            => $hasCustomPoints ? (string) ($validated['userspfx'] ?? '0') : ($budget < 500000 ? '0.5' : '1'),
            'usermufx'            => $hasCustomPoints ? (string) ($validated['usermufx'] ?? '0') : ($budget < 500000 ? '0.5' : '1'),
            'useranimals'         => (string) ($validated['useranimals'] ?? '0'),
            'uservfx'             => $hasCustomPoints ? (string) ($validated['uservfx'] ?? '0') : ($budget < 500000 ? '0' : '3'),
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

    public function testDeliver(Request $request)
    {
        $this->authorize('edit-budget-admin');

        $request->validate([
            'budget'        => 'required|numeric|min:25000|max:250000000',
            'test_email'    => 'required|email|max:255',
            'topsheet_only' => 'nullable|boolean',
        ]);

        // The delivery form passes the exact same input array that produced the calculation results
        $input = $request->except(['_token', 'test_email', 'topsheet_only']);
        $budget = (float) ($input['budget'] ?? 0);

        try {
            $service = new BudgetCalculationService();
            $payload = $service->calculate($input);

            $order = BudgetOrder::create([
                'woo_order_id'     => 'TEST-' . now()->format('YmdHis'),
                'customer_name'    => 'Test User',
                'customer_email'   => $request->input('test_email'),
                'budget_amount'    => $budget,
                'budget_class'     => $payload['budgetclass'] ?? 1,
                'state'            => $input['shootingstate'] ?? 'California',
                'guild_sag'        => ($input['usersag'] ?? '0') === '1',
                'guild_wga'        => ($input['userwga'] ?? '0') === '1',
                'guild_dga'        => ($input['userdga'] ?? '0') === '1',
                'guild_iatse'      => ($input['useriatse'] ?? '0') === '1',
                'guild_teamsters'  => ($input['userteamsters'] ?? '0') === '1',
                'weeks_prep'       => (float) ($payload['weeksPREP'] ?? 0),
                'weeks_shoot'      => (float) ($payload['weeksSHOOT'] ?? 0),
                'weeks_wrap'       => (float) ($payload['weeksWRAP'] ?? 0),
                'weeks_post'       => (float) ($payload['weeksPOST'] ?? 0),
                'cast_size'        => (int) ($input['usercastsize'] ?? 4),
                'topsheet_only'    => (bool) $request->input('topsheet_only', false),
                'header_data'      => ['title' => $input['headertitle'] ?? 'Test Budget', 'name_first' => 'Test', 'name_last' => 'User', 'date' => $input['headerdate'] ?? ''],
                'form_input_data'  => $input,
                'payload_json'     => $payload,
                'status'           => BudgetOrder::STATUS_PROCESSING,
            ]);

            GenerateBudgetFiles::dispatch($order->id);

            return redirect()->route('budget-admin.test')
                ->with('test_input', $input)
                ->with('test_delivery', [
                    'order_id' => $order->id,
                    'email'    => $request->input('test_email'),
                    'topsheet' => (bool) $request->input('topsheet_only', false),
                    'budget'   => $budget,
                ])
                ->with('success', 'Budget order #' . $order->id . ' created and queued for delivery to ' . $request->input('test_email'));
        } catch (\Throwable $e) {
            return redirect()->route('budget-admin.test')
                ->with('test_input', $input)
                ->withErrors(['delivery' => $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine()]);
        }
    }

    public function testBatch(Request $request)
    {
        $this->authorize('edit-budget-admin');

        $count = min(50, max(1, (int) $request->input('batch_count', 10)));
        $states = StateRate::pluck('state_name')->toArray();
        $guildOptions = ['all', 'sag_only', 'none'];
        $budgetRanges = [
            [25000, 49999], [50000, 199999], [200000, 499999],
            [500000, 1999999], [2000000, 3499999], [3500000, 10999999],
            [11000000, 24999999], [25000000, 100000000],
        ];

        $results = [];
        $service = new BudgetCalculationService();

        for ($t = 0; $t < $count; $t++) {
            $range = $budgetRanges[array_rand($budgetRanges)];
            $budget = rand($range[0], $range[1]);
            $guilds = $guildOptions[array_rand($guildOptions)];
            $state = $states[array_rand($states)];
            $castCount = rand(1, 15);

            // Random customization points summing to 10
            $remaining = 10;
            $points = [];
            foreach (['cast', 'stunts', 'travel', 'spfx', 'mufx', 'animals'] as $cat) {
                $val = rand(0, min($remaining, 6));
                $points[$cat] = $val;
                $remaining -= $val;
            }
            $points['vfx'] = $remaining;

            $input = [
                'budget' => $budget, 'shootingstate' => $state,
                'userusetimedefaults' => '1', 'usercastsize' => (string) $castCount,
                'usercast' => (string) $points['cast'], 'userstunts' => (string) $points['stunts'],
                'usertravel' => (string) $points['travel'], 'userspfx' => (string) $points['spfx'],
                'usermufx' => (string) $points['mufx'], 'useranimals' => (string) $points['animals'],
                'uservfx' => (string) $points['vfx'],
                'userweeksprep' => '0', 'userweeksshoot' => '0', 'userweekswrap' => '0', 'userweekspost' => '0',
                'headertitle' => 'Batch Test', 'headernamefirst' => 'Test', 'headernamelast' => 'User',
                'headerdirector' => '', 'headerdate' => now()->format('m/d/Y'),
                'budgettype' => 'Feature or Short Film', 'projecttitle' => 'Batch Test',
            ];

            if ($guilds === 'all') {
                $input += ['usersag' => '1', 'userwga' => '1', 'userdga' => '1', 'useriatse' => '1', 'userteamsters' => '1'];
            } elseif ($guilds === 'none') {
                $input += ['usersag' => '0', 'userwga' => '0', 'userdga' => '0', 'useriatse' => '0', 'userteamsters' => '0'];
            } else {
                $input += ['usersag' => '1', 'userwga' => '0', 'userdga' => '0', 'useriatse' => '0', 'userteamsters' => '0'];
            }

            for ($i = 1; $i <= 25; $i++) {
                $input['cast' . str_pad($i, 2, '0', STR_PAD_LEFT)] = $i <= $castCount ? "Cast {$i}" : '';
            }

            $start = microtime(true);
            try {
                $payload = $service->calculate($input);
                $elapsed = round((microtime(true) - $start) * 1000);

                $budgetAmt = (float) ($payload['budget'] ?? 0);
                $contingency = (float) ($payload['contingency_total'] ?? 0);
                $presurplus = (float) ($payload['presurplus_total_FINAL'] ?? 0);
                $surplusKeys = ['_570additionalcastbudget','_572agentattyfees','_610stuntslaborbudget','_620purchases','_630rentals','_640boxrentals','_699miscexpenses','_710travel','_720lodging','_799miscexpenses','_2310makeupfxlaborbudget','_2312materials','_2314rentals','_2399miscexpenses','_2410specialfxlaborbudget','_2412purchasesrentals','_2414manufacturing','_2416riggingstriking','_2418boxrentals','_2499miscexpenses','_2510animalslaborbudget','_2512animals','_2514animalcarefeeding','_2516animaltravellodging','_2810visualfxbudget'];
                $surplusTotal = 0;
                foreach ($surplusKeys as $k) $surplusTotal += (float) ($payload[$k] ?? 0);
                $engineTotal = $presurplus + $contingency + $surplusTotal;
                $gap = $budgetAmt - $engineTotal;

                $results[] = [
                    'budget' => $budgetAmt, 'class' => $payload['budgetclass'] ?? '?',
                    'state' => $state, 'guilds' => $guilds,
                    'cast' => $castCount, 'points' => implode('/', array_values($points)),
                    'engine_total' => $engineTotal, 'gap' => $gap,
                    'pass' => abs($gap) < 1, 'ms' => $elapsed, 'error' => null,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'budget' => $budget, 'class' => '?', 'state' => $state, 'guilds' => $guilds,
                    'cast' => $castCount, 'points' => implode('/', array_values($points)),
                    'engine_total' => 0, 'gap' => $budget, 'pass' => false,
                    'ms' => round((microtime(true) - $start) * 1000),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return redirect()->route('budget-admin.test')
            ->with('batch_results', $results);
    }
}
