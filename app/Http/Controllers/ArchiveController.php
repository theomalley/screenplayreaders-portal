<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\FollowupQuestion;
use App\Support\Permission;

class ArchiveController extends Controller
{
    public function index()
    {
        abort_unless(Permission::check('archive'), 403);

        // Completed — grouped by order number so multi-reader orders appear as one row.
        $groups = Assignment::with(['assignedReader.readerProfile', 'coverageSubmission'])
            ->where('status', Assignment::STATUS_COMPLETED)
            ->get()
            ->groupBy('order_number')
            ->sortByDesc(fn($group) => $group->max(fn($a) => $a->completed_at?->timestamp ?? 0));

        $ordersWithSubmissions = FollowupQuestion::whereIn('order_number', $groups->keys())
            ->pluck('order_number')
            ->unique()
            ->flip()
            ->all();

        // Cancelled — grouped by order number, sorted by most recently created.
        $cancelled = Assignment::with(['assignedReader.readerProfile'])
            ->where('status', Assignment::STATUS_CANCELLED)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('order_number');

        return view('archive.index', compact('groups', 'ordersWithSubmissions', 'cancelled'));
    }
}
