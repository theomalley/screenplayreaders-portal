<?php

// v1.7 — 2026-07-23 | Sync every webhook-created assignment to Tier 1 — previously these
//                     landed with no tier at all, making them invisible in the default
//                     "grouped by tier" admin/reader views (order 58166 incident).
// v1.6 — 2026-06-13 | Accept block_initials (hyphen-separated reader initials the customer
//                     blocked on the upload form) and resolve to blocked_reader_ids on every
//                     slot created for the order.
// v1.5 — 2026-05-28 | Deep-Dive Dev Notes includes a free reader request — exclude request fee from pay rate.
// v1.4.1 — 2026-05-24 | Force redeploy with formatting upload fixes.
// v1.4 — 2026-05-24 | Preserve original file extension in stored filename so UploadScriptToDrive
//                     gets the correct ext — store() guesses MIME-based ext (e.g. zip for .fadein).
// v1.3 — 2026-05-22 | Inline pay rate computation, per-slot try/catch, service token logging.
// v1.2 — 2026-05-22 | Multi-reader support, service token mapping, reader request resolution,
//                     nullable page_count, idempotency guard.
// v1.1 — 2026-05-19 | Full implementation — validates webhook secret, creates assignment,
//                     stores uploaded PDF temporarily, dispatches Drive upload job.
//                     PORTAL INTEGRATION: endpoint called by WordPress sr-upload-system.php
//                     after a customer completes checkout and uploads their script.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\UploadScriptToDrive;
use App\Models\Assignment;
use App\Models\Setting;
use App\Models\Tier;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IncomingAssignmentController extends Controller
{
    /**
     * Maps WordPress service tokens (from SR_UPLOAD_SERVICE_TOKENS) to one
     * assignment_type per reader slot. Multi-reader coverage gets one row per slot.
     */
    private const SERVICE_SLOTS = [
        'coverage'      => ['script_coverage'],
        'coverage2r'    => ['script_coverage', 'notes_only'],
        'coverage3r'    => ['script_coverage', 'notes_only', 'notes_only'],
        'devnotes'      => ['deep_dive'],
        'shortcoverage' => ['short'],
        'proofreading'  => ['proofreading'],
        'formatting'    => ['formatting'],
    ];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_number'     => 'required|string|max:64',
            'service'          => 'required|string|max:64',
            'script_title'     => 'required|string|max:255',
            'writer_name'      => 'nullable|string|max:255',
            'page_count'       => 'nullable|integer|min:1',
            'rush'             => 'nullable|boolean',
            'proofreading'     => 'nullable|boolean',
            'reader_request_1' => 'nullable|string|max:20',
            'reader_request_2' => 'nullable|string|max:20',
            'reader_request_3' => 'nullable|string|max:20',
            'block_initials'   => 'nullable|string|max:255',
            'script'           => 'required|file|mimes:pdf,docx|max:5120',
        ]);

        Log::info('IncomingAssignment: received', [
            'order_number' => $data['order_number'],
            'service'      => $data['service'],
            'page_count'   => $data['page_count'] ?? null,
            'rush'         => $data['rush'] ?? false,
        ]);

        // Idempotency — if assignments already exist for this order, skip silently
        if (Assignment::where('order_number', $data['order_number'])->exists()) {
            return response()->json(['status' => 'already_exists'], 200);
        }

        $slots = self::SERVICE_SLOTS[strtolower($data['service'])] ?? null;
        if (! $slots) {
            return response()->json(['error' => 'Unknown service: ' . $data['service']], 422);
        }

        // Resolve reader request initials → User IDs via readerProfile.initials
        $readerIds = [];
        foreach ($slots as $i => $_) {
            $initials = strtoupper((string) $request->input('reader_request_' . ($i + 1), ''));
            if ($initials && $initials !== 'FIRST_AVAILABLE') {
                $user = User::whereHas('readerProfile', fn ($q) => $q->where('initials', $initials))->first();
                $readerIds[$i] = $user?->id;
            } else {
                $readerIds[$i] = null;
            }
        }

        // Resolve block initials ("AB-CD") → User IDs via readerProfile.initials.
        // Applied to every slot created for this order, since a customer-blocked
        // reader shouldn't be eligible for any slot of the order.
        $blockedReaderIds = [];
        $blockTokens = array_filter(explode('-', strtoupper((string) ($data['block_initials'] ?? ''))));
        foreach ($blockTokens as $initials) {
            $user = User::whereHas('readerProfile', fn ($q) => $q->where('initials', $initials))->first();
            if ($user) {
                $blockedReaderIds[] = $user->id;
            }
        }

        $rates        = Setting::ratesForForms();
        $pageCount    = (int) ($data['page_count'] ?? 0);
        $rush         = (bool) ($data['rush'] ?? false);
        $proofreading = (bool) ($data['proofreading'] ?? false);

        // Create one Assignment row per reader slot
        $assignments = [];
        foreach ($slots as $i => $type) {
            try {
                $payRate = $this->computePayRate($rates, $type, $rush, $pageCount, $readerIds[$i] ?? null);

                $assignments[] = Assignment::create([
                    'order_number'        => $data['order_number'],
                    'vendor'              => 'sr',
                    'assignment_type'     => $type,
                    'proofreading'        => $proofreading && $i === 0,
                    'script_title'        => $data['script_title'],
                    'writer_name'         => $data['writer_name'] ?? '',
                    'page_count'          => $data['page_count'] ?? null,
                    'rush'                => $rush,
                    'pay_rate'            => $payRate,
                    'status'              => Assignment::STATUS_INCOMING,
                    'requested_reader_id' => $readerIds[$i] ?? null,
                    'blocked_reader_ids'  => $blockedReaderIds ?: null,
                ]);
            } catch (\Throwable $e) {
                Log::error('IncomingAssignment: slot create failed', [
                    'order_number' => $data['order_number'],
                    'slot'         => $i,
                    'type'         => $type,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        if (empty($assignments)) {
            return response()->json(['error' => 'All assignment creates failed.'], 500);
        }

        // Webhook-created assignments have no tier picker (unlike the manual Create
        // Assignment form), so without this they land in "No Tier Assigned" and are
        // invisible in the default admin/reader views. Default every slot to Tier 1.
        $tierOne = Tier::where('is_onboarding', false)->orderBy('position')->first();
        if ($tierOne) {
            foreach ($assignments as $assignment) {
                $assignment->tiers()->sync([$tierOne->id]);
            }
        } else {
            Log::error('IncomingAssignment: no Tier 1 found to assign', ['order_number' => $data['order_number']]);
        }

        // Stash the file and dispatch an async Drive upload (keyed to first assignment).
        // Use storeAs() with the client's original extension — store() uses guessExtension()
        // which returns the MIME-based ext (e.g. 'zip' for .fadein) not the actual filename ext.
        $clientExt   = strtolower($request->file('script')->getClientOriginalExtension());
        $storageName = Str::random(40) . ($clientExt !== '' ? '.' . $clientExt : '');
        $storagePath = $request->file('script')->storeAs('incoming-scripts', $storageName, 'local');
        if ($storagePath === false) {
            Log::error('IncomingAssignment: file store failed', ['order_number' => $data['order_number']]);
            return response()->json(['error' => 'File storage failed.'], 500);
        }
        UploadScriptToDrive::dispatch($assignments[0]->id, $storagePath);

        return response()->json([
            'order_number' => $data['order_number'],
            'assignments'  => count($assignments),
        ], 201);
    }

    private function computePayRate(array $rates, string $type, bool $rush, int $pageCount, ?int $requestedReaderId): float
    {
        $baseMap = [
            'script_coverage' => $rates['rate_sr_script_coverage'],
            'notes_only'      => $rates['rate_sr_notes_only'],
            'deep_dive'       => $rates['rate_sr_deep_dive'],
            'short'           => $rates['rate_sr_short'],
            'budget'          => $rates['rate_sr_budget'],
            'proofreading'    => $rates['rate_sr_proofreading'],
            'formatting'      => 0.00,
        ];

        $total = (float) ($baseMap[$type] ?? 0);

        if ($pageCount >= 121 && $pageCount <= 160) {
            $total += (float) $rates['rate_sr_oversized_121_160'];
        }

        if ($rush) {
            $total += (float) $rates['rate_sr_rush'];
        }

        // Advanced Script Coverage (deep_dive) includes a free reader request — never add the fee
        if ($requestedReaderId && $type !== 'deep_dive') {
            $total += (float) $rates['rate_sr_request'];
        }

        return round($total, 2);
    }
}
