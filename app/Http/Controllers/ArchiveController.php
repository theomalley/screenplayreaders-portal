<?php

namespace App\Http\Controllers;

use App\Models\Assignment;

class ArchiveController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $assignments = Assignment::with(['assignedReader.readerProfile'])
            ->where('status', Assignment::STATUS_COMPLETED)
            ->orderByDesc('completed_at')
            ->paginate(50);

        return view('archive.index', compact('assignments'));
    }
}
