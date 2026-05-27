<?php

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

        return view('team.index', [
            'editors'          => $editors,
            'readers'          => $readers,
            'canEditEditors'   => Permission::check('editors.edit'),
            'canDeleteEditors' => Permission::check('editors.delete'),
            'canEditReaders'   => Permission::check('readers.edit'),
            'canDeleteReaders' => Permission::check('readers.delete'),
        ]);
    }
}
