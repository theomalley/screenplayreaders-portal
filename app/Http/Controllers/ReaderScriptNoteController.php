<?php

// v1.1 — 2026-06-05 | Add index() to list notes for a given assignment (used by reader modal lazy-load).
// v1.0 — 2026-06-05 | AJAX CRUD for personal reading notes on a script assignment.

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\ReaderScriptNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReaderScriptNoteController extends Controller
{
    public function index(Assignment $assignment): JsonResponse
    {
        $notes = ReaderScriptNote::where('assignment_id', $assignment->id)
            ->where('user_id', auth()->id())
            ->orderBy('created_at')
            ->get(['id', 'body', 'created_at']);

        return response()->json($notes->map(fn($n) => [
            'id'         => $n->id,
            'body'       => $n->body,
            'created_at' => $n->created_at->format('M j, g:ia'),
        ]));
    }

    public function store(Request $request, Assignment $assignment): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $note = ReaderScriptNote::create([
            'assignment_id' => $assignment->id,
            'user_id'       => auth()->id(),
            'body'          => $data['body'],
        ]);

        return response()->json([
            'id'         => $note->id,
            'body'       => $note->body,
            'created_at' => $note->created_at->format('M j, g:ia'),
        ], 201);
    }

    public function destroy(ReaderScriptNote $note): JsonResponse
    {
        abort_unless($note->user_id === auth()->id(), 403);

        $note->delete();

        return response()->json(['ok' => true]);
    }
}
