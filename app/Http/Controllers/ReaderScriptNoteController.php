<?php

// v1.2 — 2026-06-09 | Capture and return page_number — auto-logged from the PDF viewer's current page.
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
            ->get(['id', 'body', 'page_number', 'created_at']);

        return response()->json($notes->map(fn($n) => [
            'id'          => $n->id,
            'body'        => $n->body,
            'page_number' => $n->page_number,
            'created_at'  => $n->created_at->format('M j, g:ia'),
        ]));
    }

    public function store(Request $request, Assignment $assignment): JsonResponse
    {
        $data = $request->validate([
            'body'        => ['required', 'string', 'max:2000'],
            'page_number' => ['nullable', 'integer', 'min:1'],
        ]);

        $note = ReaderScriptNote::create([
            'assignment_id' => $assignment->id,
            'user_id'       => auth()->id(),
            'body'          => $data['body'],
            'page_number'   => $data['page_number'] ?? null,
        ]);

        return response()->json([
            'id'          => $note->id,
            'body'        => $note->body,
            'page_number' => $note->page_number,
            'created_at'  => $note->created_at->format('M j, g:ia'),
        ], 201);
    }

    public function destroy(ReaderScriptNote $note): JsonResponse
    {
        abort_unless($note->user_id === auth()->id(), 403);

        $note->delete();

        return response()->json(['ok' => true]);
    }
}
