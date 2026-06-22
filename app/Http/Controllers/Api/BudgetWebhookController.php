<?php

// v1.0 — 2026-06-21 | Initial: receives budget order webhook from WooCommerce,
//                      maps GF fields, creates BudgetOrder, dispatches calculation job.
//                      PORTAL INTEGRATION: endpoint called by woo_budgeting.php
//                      after a customer completes checkout for product 55672.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBudgetOrder;
use App\Models\Budget\BudgetOrder;
use App\Services\Budget\GravityFormFieldMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BudgetWebhookController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! $this->authorised($request)) {
            return response()->json(['error' => 'Unauthorised.'], 401);
        }

        $data = $request->validate([
            'order_id'       => 'required|string|max:64',
            'customer_name'  => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'product_id'     => 'required',
            'form_entry_id'  => 'nullable|string|max:64',
        ]);

        Log::info('BudgetWebhook: received', [
            'order_id' => $data['order_id'],
            'customer_email' => $data['customer_email'],
        ]);

        // Idempotency — skip if we already have this order
        if (BudgetOrder::where('woo_order_id', $data['order_id'])->exists()) {
            return response()->json(['status' => 'already_exists'], 200);
        }

        // Map GF field labels to engine variable names
        $mapper = new GravityFormFieldMapper();
        $mapped = $mapper->map($request->all());

        $budget = (float) ($mapped['budget'] ?? 0);

        // Determine budget class for storage
        $budgetClass = $this->determineBudgetClass($budget);

        // Determine product type (topsheet-only vs full)
        $budgetFormat = $mapped['budgetformat'] ?? ($mapped['budget_format'] ?? '');
        $topsheetOnly = ($budgetFormat === '67');

        $order = BudgetOrder::create([
            'woo_order_id'    => $data['order_id'],
            'customer_name'   => $data['customer_name'],
            'customer_email'  => $data['customer_email'],
            'form_entry_id'   => $data['form_entry_id'] ?? null,
            'budget_amount'   => $budget,
            'budget_class'    => $budgetClass,
            'state'           => $mapped['shootingstate'] ?? null,
            'guild_wga'       => !empty($mapped['userwga']),
            'guild_dga'       => !empty($mapped['userdga']),
            'guild_sag'       => !empty($mapped['usersag']),
            'guild_iatse'     => !empty($mapped['useriatse']),
            'guild_teamsters' => !empty($mapped['userteamsters']),
            'sag_student'     => !empty($mapped['usersagstudent']),
            'sag_short'       => !empty($mapped['usersagshort']),
            'weeks_prep'      => (float) ($mapped['userweeksprep'] ?? 0),
            'weeks_shoot'     => (float) ($mapped['userweeksshoot'] ?? 0),
            'weeks_wrap'      => (float) ($mapped['userweekswrap'] ?? 0),
            'weeks_post'      => (float) ($mapped['userweekspost'] ?? 0),
            'use_time_defaults' => (int) ($mapped['userusetimedefaults'] ?? 1) === 1,
            'cast_size'       => (int) ($mapped['usercastsize'] ?? 0),
            'cast_data'       => $this->extractCastData($mapped),
            'surplus_cast'    => (float) ($mapped['usercast'] ?? 0),
            'surplus_stunts'  => (float) ($mapped['userstunts'] ?? 0),
            'surplus_travel'  => (float) ($mapped['usertravel'] ?? 0),
            'surplus_spfx'    => (float) ($mapped['userspfx'] ?? 0),
            'surplus_mufx'    => (float) ($mapped['usermufx'] ?? 0),
            'surplus_animals' => (float) ($mapped['useranimals'] ?? 0),
            'surplus_vfx'     => (float) ($mapped['uservfx'] ?? 0),
            'header_data'     => $this->extractHeaderData($mapped),
            'form_input_data' => $mapped,
            'topsheet_only'   => $topsheetOnly,
            'status'          => BudgetOrder::STATUS_PENDING,
        ]);

        ProcessBudgetOrder::dispatch($order->id);

        return response()->json([
            'status' => 'accepted',
            'budget_order_id' => $order->id,
        ], 202);
    }

    private function authorised(Request $request): bool
    {
        $secret = config('services.portal.webhook_secret');

        return ! empty($secret) && hash_equals($secret, $request->bearerToken() ?? '');
    }

    private function determineBudgetClass(float $budget): int
    {
        $ranges = [
            [25000, 49999.99, 1], [50000, 199999.99, 2],
            [200000, 499999.99, 3], [500000, 1999999.99, 4],
            [2000000, 3499999.99, 5], [3500000, 10999999.99, 6],
            [11000000, 24999999.99, 7], [25000000, 250000000, 8],
        ];

        foreach ($ranges as [$min, $max, $cls]) {
            if ($budget >= $min && $budget <= $max) {
                return $cls;
            }
        }

        return 1;
    }

    private function extractCastData(array $mapped): array
    {
        $cast = [];
        for ($i = 1; $i <= 25; $i++) {
            $key = 'cast' . str_pad($i, 2, '0', STR_PAD_LEFT);
            if (!empty($mapped[$key])) {
                $cast[] = $mapped[$key];
            }
        }
        return $cast;
    }

    private function extractHeaderData(array $mapped): array
    {
        $title = $mapped['headertitle'] ?? '';
        if ($title === '') {
            $title = $mapped['projecttitle'] ?? '';
        }

        return array_filter([
            'title'          => $title,
            'director'       => $mapped['headerdirector'] ?? '',
            'date'           => $mapped['headerdate'] ?? '',
            'name_first'     => $mapped['headernamefirst'] ?? '',
            'name_last'      => $mapped['headernamelast'] ?? '',
            'budget_type'    => $mapped['budgettype'] ?? '',
            'series_type'    => $mapped['seriestype'] ?? '',
            'episode_number' => $mapped['headerepisodenumber'] ?? '',
            'episode_title'  => $mapped['headerepisodetitle'] ?? '',
            'num_episodes'   => $mapped['headernumofepisodes'] ?? '',
        ]);
    }
}
