<?php

// v1.0 — 2026-06-02 | Store and delete internal editor notes on assignments

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentEditorNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AssignmentEditorNoteController extends Controller
{
    public function store(Request $request, Assignment $assignment): RedirectResponse
    {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);

        $request->validate(['body' => 'required|string|max:2000']);

        AssignmentEditorNote::create([
            'assignment_id' => $assignment->id,
            'user_id'       => auth()->id(),
            'body'          => trim($request->input('body')),
        ]);

        return back()->with('success', 'Editor note added.');
    }

    public function destroy(AssignmentEditorNote $note): RedirectResponse
    {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);

        $note->delete();

        return back()->with('success', 'Editor note deleted.');
    }
}
