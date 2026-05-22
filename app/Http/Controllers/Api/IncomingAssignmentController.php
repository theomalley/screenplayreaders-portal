<?php

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
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        if (! $this->authorised($request)) {
            return response()->json(['error' => 'Unauthorised.'], 401);
        }

        $data = $request->validate([
            'order_number'     => 'required|string|max:64',
            'service'          => 'required|string|max:64',
            'script_title'     => 'required|string|max:255',
            'writer_name'      => 'nullable|string|max:255',
            'page_count'       => 'nullable|integer|min:1',
            'rush'             => 'nullable|boolean',
            'reader_request_1' => 'nullable|string|max:20',
            'reader_request_2' => 'nullable|string|max:20',
            'reader_request_3' => 'nullable|string|max:20',
            'script'           => 'required|file|max:5120',
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

        // Create one Assignment row per reader slot
        $assignments = [];
        foreach ($slots as $i => $type) {
            $assignments[] = Assignment::create([
                'order_number'        => $data['order_number'],
                'vendor'              => 'sr',
                'assignment_type'     => $type,
                'script_title'        => $data['script_title'],
                'writer_name'         => $data['writer_name'] ?? '',
                'page_count'          => $data['page_count'] ?? null,
                'rush'                => $data['rush'] ?? false,
                'status'              => Assignment::STATUS_INCOMING,
                'requested_reader_id' => $readerIds[$i] ?? null,
            ]);
        }

        // Stash the file and dispatch an async Drive upload (keyed to first assignment)
        $storagePath = $request->file('script')->store('incoming-scripts');
        UploadScriptToDrive::dispatch($assignments[0]->id, $storagePath);

        return response()->json([
            'order_number' => $data['order_number'],
            'assignments'  => count($assignments),
        ], 201);
    }

    private function authorised(Request $request): bool
    {
        $secret = config('services.portal.webhook_secret');

        return ! empty($secret) && $request->bearerToken() === $secret;
    }
}
