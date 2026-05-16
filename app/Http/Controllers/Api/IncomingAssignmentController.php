<?php

// v1.0 — 2026-05-16 | Stub — receives webhook from WordPress/WooCommerce when a customer order completes.
// Endpoint: POST /api/incoming-assignment
// Payload spec TBD — see CLAUDE.md "Outstanding Decisions".

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class IncomingAssignmentController extends Controller
{
    public function store(Request $request)
    {
        // TODO: validate webhook signature, create Assignment with status=incoming,
        // dispatch job to pull script from WordPress upload and push to Google Drive
        return response()->json(['message' => 'Not yet implemented.'], 501);
    }
}
