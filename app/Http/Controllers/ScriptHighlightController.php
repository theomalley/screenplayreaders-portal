<?php

// v1.0 — 2026-06-09 | AJAX CRUD for personal PDF text highlights on a script assignment.

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\ScriptHighlight;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScriptHighlightController extends Controller
{
    public function index(Assignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        $highlights = ScriptHighlight::where('assignment_id', $assignment->id)
            ->where('user_id', auth()->id())
            ->orderBy('page_number')
            ->orderBy('created_at')
            ->get(['id', 'page_number', 'text', 'rects', 'color']);

        return response()->json($highlights->map(fn($h) => [
            'id'          => $h->id,
            'page_number' => $h->page_number,
            'text'        => $h->text,
            'rects'       => $h->rects,
            'color'       => $h->color,
        ]));
    }

    public function store(Request $request, Assignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        $data = $request->validate([
            'page_number'         => ['required', 'integer', 'min:1'],
            'text'                => ['nullable', 'string', 'max:5000'],
            'color'               => ['nullable', 'string', 'max:20'],
            'rects'               => ['required', 'array', 'min:1'],
            'rects.*.x'           => ['required', 'numeric', 'between:0,1'],
            'rects.*.y'           => ['required', 'numeric', 'between:0,1'],
            'rects.*.width'       => ['required', 'numeric', 'between:0,1'],
            'rects.*.height'      => ['required', 'numeric', 'between:0,1'],
        ]);

        $highlight = ScriptHighlight::create([
            'assignment_id' => $assignment->id,
            'user_id'       => auth()->id(),
            'page_number'   => $data['page_number'],
            'text'          => $data['text'] ?? null,
            'rects'         => $data['rects'],
            'color'         => $data['color'] ?? 'yellow',
        ]);

        return response()->json([
            'id'          => $highlight->id,
            'page_number' => $highlight->page_number,
            'text'        => $highlight->text,
            'rects'       => $highlight->rects,
            'color'       => $highlight->color,
        ], 201);
    }

    public function destroy(ScriptHighlight $highlight): JsonResponse
    {
        abort_unless($highlight->user_id === auth()->id(), 403);

        $highlight->delete();

        return response()->json(['ok' => true]);
    }
}
