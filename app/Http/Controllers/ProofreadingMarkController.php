<?php

// v1.0 — 2026-06-19 | AJAX CRUD for proofreading annotations on script PDFs.
//                     submit() dispatches PDF generation and moves assignment to QC.

namespace App\Http\Controllers;

use App\Jobs\GenerateProofreadPdf;
use App\Models\Assignment;
use App\Models\ProofreadingMark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProofreadingMarkController extends Controller
{
    public function index(Assignment $assignment): JsonResponse
    {
        $marks = ProofreadingMark::where('assignment_id', $assignment->id)
            ->where('user_id', auth()->id())
            ->orderBy('page_number')
            ->orderBy('created_at')
            ->get(['id', 'page_number', 'type', 'data']);

        return response()->json($marks);
    }

    public function store(Request $request, Assignment $assignment): JsonResponse
    {
        $data = $request->validate([
            'page_number' => ['required', 'integer', 'min:1'],
            'type'        => ['required', 'string', 'in:strikethrough,arrow,note'],
            'data'        => ['required', 'array'],
        ]);

        $this->validateMarkData($data['type'], $data['data']);

        $mark = ProofreadingMark::create([
            'assignment_id' => $assignment->id,
            'user_id'       => auth()->id(),
            'page_number'   => $data['page_number'],
            'type'          => $data['type'],
            'data'          => $data['data'],
        ]);

        return response()->json([
            'id'          => $mark->id,
            'page_number' => $mark->page_number,
            'type'        => $mark->type,
            'data'        => $mark->data,
        ], 201);
    }

    public function update(Request $request, ProofreadingMark $mark): JsonResponse
    {
        abort_unless($mark->user_id === auth()->id(), 403);

        $data = $request->validate([
            'data' => ['required', 'array'],
        ]);

        $this->validateMarkData($mark->type, $data['data']);

        $mark->update(['data' => $data['data']]);

        return response()->json([
            'id'          => $mark->id,
            'page_number' => $mark->page_number,
            'type'        => $mark->type,
            'data'        => $mark->data,
        ]);
    }

    public function destroy(ProofreadingMark $mark): JsonResponse
    {
        abort_unless($mark->user_id === auth()->id(), 403);

        $mark->delete();

        return response()->json(['ok' => true]);
    }

    public function submit(Assignment $assignment)
    {
        abort_unless(
            $assignment->assigned_reader_id === auth()->id()
            && in_array($assignment->status, [Assignment::STATUS_ASSIGNED, Assignment::STATUS_NEEDS_ATTENTION], true),
            403
        );

        $markCount = ProofreadingMark::where('assignment_id', $assignment->id)->count();
        if ($markCount === 0) {
            return back()->with('error', 'No proofreading marks to submit.');
        }

        GenerateProofreadPdf::dispatch($assignment->id);

        $assignment->update([
            'status'       => Assignment::STATUS_QC,
            'submitted_at' => now(),
        ]);

        return redirect()->route('assignments.index')
            ->with('success', "Proofreading submitted for #{$assignment->order_number} — {$assignment->script_title}.");
    }

    private function validateMarkData(string $type, array $data): void
    {
        match ($type) {
            'strikethrough' => validator($data, [
                'rects'       => ['required', 'array', 'min:1'],
                'rects.*.x'  => ['required', 'numeric', 'between:0,1'],
                'rects.*.y'  => ['required', 'numeric', 'between:0,1'],
                'rects.*.w'  => ['required', 'numeric', 'between:0,1'],
                'rects.*.h'  => ['required', 'numeric', 'between:0,1'],
                'text'       => ['nullable', 'string', 'max:5000'],
                'correction' => ['nullable', 'string', 'max:5000'],
            ])->validate(),
            'arrow' => validator($data, [
                'start'   => ['required', 'array'],
                'start.x' => ['required', 'numeric', 'between:0,1'],
                'start.y' => ['required', 'numeric', 'between:0,1'],
                'end'     => ['required', 'array'],
                'end.x'   => ['required', 'numeric', 'between:0,1'],
                'end.y'   => ['required', 'numeric', 'between:0,1'],
            ])->validate(),
            'note' => validator($data, [
                'position'   => ['required', 'array'],
                'position.x' => ['required', 'numeric', 'between:0,1'],
                'position.y' => ['required', 'numeric', 'between:0,1'],
                'text'       => ['required', 'string', 'max:5000'],
            ])->validate(),
        };
    }
}
