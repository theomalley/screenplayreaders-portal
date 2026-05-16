<?php

// v1.0 — 2026-05-16 | Stub — Assignments Module CRUD + status transitions.
// Full implementation follows after Breeze auth is in place.

namespace App\Http\Controllers;

use App\Models\Assignment;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function index()
    {
        // TODO: Phase 1 — split view for readers (available + mine), full list for admin/editor
    }

    public function create()
    {
        // TODO: admin/editor only — gate via AssignmentPolicy::create
    }

    public function store(Request $request)
    {
        // TODO: validate via Form Request, upload script to Drive via GoogleDriveService job
    }

    public function show(Assignment $assignment)
    {
        // TODO: gate via AssignmentPolicy::view
    }

    public function edit(Assignment $assignment)
    {
        // TODO: gate via AssignmentPolicy::update
    }

    public function update(Request $request, Assignment $assignment)
    {
        // TODO: validate + update; handle status transitions with side effects
    }

    public function accept(Assignment $assignment)
    {
        // TODO: DB transaction with SELECT FOR UPDATE; check reader capacity; gate via AssignmentPolicy::accept
    }

    public function cancel(Assignment $assignment)
    {
        // TODO: revert to unassigned; gate via AssignmentPolicy::cancel
    }
}
