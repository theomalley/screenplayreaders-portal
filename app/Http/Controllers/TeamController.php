<?php

// v1.2 — 2026-05-30 | Pass pendingApprovals count to view.
// v1.1 — 2026-05-27 | Include admins section; reader photo upload; fix admin photo in assigned-reader column.
// v1.0 — 2026-05-27 | Combined Team view — editors and readers on one unified list

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\User;
use App\Support\Permission;

class TeamController extends Controller
{
    public function index()
    {
        abort_unless(Permission::check('team'), 403);

        $admins = User::where('role', 'admin')
            ->with('editorProfile')
            ->orderBy('name')
            ->get();

        $editors = User::where('role', 'editor')
            ->with('editorProfile')
            ->withCount([
                'assignments as active_count'    => fn($q) => $q->where('status', Assignment::STATUS_ASSIGNED),
                'assignments as completed_count' => fn($q) => $q->where('status', Assignment::STATUS_COMPLETED),
                'assignments as total_count',
            ])
            ->orderBy('name')
            ->get();

        $readers = User::where('role', 'reader')
            ->with('readerProfile')
            ->withCount([
                'assignments as active_count'    => fn($q) => $q->where('status', Assignment::STATUS_ASSIGNED),
                'assignments as completed_count' => fn($q) => $q->where('status', Assignment::STATUS_COMPLETED),
                'assignments as total_count',
            ])
            ->orderBy('name')
            ->get();

        $pendingApprovals = 0;
        foreach ($readers as $r) {
            if ($r->readerProfile?->bio_pending !== null)  $pendingApprovals++;
            if ($r->readerProfile?->photo_pending)         $pendingApprovals++;
        }
        foreach ($editors as $e) {
            if ($e->editorProfile?->bio_pending !== null)  $pendingApprovals++;
            if ($e->editorProfile?->photo_pending)         $pendingApprovals++;
        }

        return view('team.index', [
            'admins'            => $admins,
            'editors'           => $editors,
            'readers'           => $readers,
            'canEditEditors'    => Permission::check('editors.edit'),
            'canDeleteEditors'  => Permission::check('editors.delete'),
            'canEditReaders'    => Permission::check('readers.edit'),
            'canDeleteReaders'  => Permission::check('readers.delete'),
            'pendingApprovals'  => $pendingApprovals,
        ]);
    }
}
