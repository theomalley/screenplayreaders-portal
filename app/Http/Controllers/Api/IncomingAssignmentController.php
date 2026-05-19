<?php

// v1.1 — 2026-05-19 | Full implementation — validates webhook secret, creates assignment,
//                     stores uploaded PDF temporarily, dispatches Drive upload job.
//                     PORTAL INTEGRATION: endpoint called by WordPress sr-upload-system.php
//                     after a customer completes checkout and uploads their script.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\UploadScriptToDrive;
use App\Models\Assignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncomingAssignmentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! $this->authorised($request)) {
            return response()->json(['error' => 'Unauthorised.'], 401);
        }

        $data = $request->validate([
            'order_number'  => 'required|string|max:64',
            'vendor'        => 'required|in:sr,wd',
            'script_title'  => 'required|string|max:255',
            'writer_name'   => 'required|string|max:255',
            'page_count'    => 'required|integer|min:1',
            'rush'          => 'boolean',
            'pay_rate'      => 'required|numeric|min:0',
            'notes'         => 'nullable|string',
            'public_opt_in' => 'boolean',
            'script'        => 'required|file|mimes:pdf|max:51200', // 50 MB
        ]);

        $assignment = Assignment::create([
            'order_number'  => $data['order_number'],
            'vendor'        => $data['vendor'],
            'script_title'  => $data['script_title'],
            'writer_name'   => $data['writer_name'],
            'page_count'    => $data['page_count'],
            'rush'          => $data['rush'] ?? false,
            'pay_rate'      => $data['pay_rate'],
            'notes'         => $data['notes'] ?? null,
            'public_opt_in' => $data['public_opt_in'] ?? false,
            'status'        => Assignment::STATUS_INCOMING,
        ]);

        // Store PDF in a private temporary location; Drive upload runs async in queue
        $storagePath = $request->file('script')->store('incoming-scripts');
        UploadScriptToDrive::dispatch($assignment->id, $storagePath);

        return response()->json([
            'assignment_id' => $assignment->id,
            'status'        => $assignment->status,
        ], 201);
    }

    private function authorised(Request $request): bool
    {
        $secret = config('services.portal.webhook_secret');

        return ! empty($secret) && $request->bearerToken() === $secret;
    }
}
