<?php

// v1.0 — 2026-05-31 | Reader notes on assignments + admin replies; per-user dismissal

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentNote;
use App\Models\AssignmentNoteReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AssignmentNoteController extends Controller
{
    public function store(Request $request, Assignment $assignment): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->isReader(), 403);

        $request->validate(['body' => 'required|string|max:1000']);

        $existing = AssignmentNote::where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->count();

        abort_if($existing >= 3, 422, 'Maximum 3 notes per assignment.');

        AssignmentNote::create([
            'assignment_id' => $assignment->id,
            'user_id'       => $user->id,
            'body'          => trim($request->input('body')),
            'dismissed_by'  => [],
        ]);

        return back()->with('success', 'Note sent to the team.');
    }

    public function reply(Request $request, AssignmentNote $note): RedirectResponse
    {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);

        $request->validate(['body' => 'required|string|max:1000']);

        AssignmentNoteReply::create([
            'assignment_note_id' => $note->id,
            'user_id'            => auth()->id(),
            'body'               => trim($request->input('body')),
            'dismissed_by'       => [],
        ]);

        return back()->with('success', 'Reply sent.');
    }

    public function dismiss(AssignmentNote $note): JsonResponse
    {
        $note->dismiss(auth()->id());
        return response()->json(['status' => 'ok']);
    }

    public function dismissReply(AssignmentNoteReply $reply): JsonResponse
    {
        $reply->dismiss(auth()->id());
        return response()->json(['status' => 'ok']);
    }
}
