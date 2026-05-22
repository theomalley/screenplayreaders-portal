<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Support\Permission;

class ArchiveController extends Controller
{
    public function index()
    {
        abort_unless(Permission::check('archive'), 403);

        // Fetch all completed assignments and group by order number so multi-reader
        // orders (2R, 3R) appear as a single row with one coverage link per reader.
        $groups = Assignment::with(['assignedReader.readerProfile'])
            ->where('status', Assignment::STATUS_COMPLETED)
            ->get()
            ->groupBy('order_number')
            ->sortByDesc(fn($group) => $group->max(fn($a) => $a->completed_at?->timestamp ?? 0));

        return view('archive.index', compact('groups'));
    }
}
