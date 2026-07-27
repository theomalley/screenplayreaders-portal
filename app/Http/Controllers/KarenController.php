<?php

// v1.0 — 2026-07-27 | Karen List — customers we never do business with again.
//                     Matched against incoming assignments in
//                     Api\IncomingAssignmentController::store().

namespace App\Http\Controllers;

use App\Models\Karen;
use Illuminate\Http\Request;

class KarenController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Karen::class);

        $karens = Karen::orderBy('last_name')->orderBy('first_name')->get();

        return view('karens.index', compact('karens'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Karen::class);

        $data = $this->validate($request);
        $data['flagged_date'] = $data['flagged_date'] ?? now()->toDateString();

        $karen = Karen::create($data);

        if ($request->wantsJson()) {
            return response()->json(['karen' => $karen]);
        }

        return back()->with('success', 'Karen added.');
    }

    public function update(Request $request, Karen $karen)
    {
        $this->authorize('update', $karen);

        $karen->update($this->validate($request));

        if ($request->wantsJson()) {
            return response()->json(['karen' => $karen]);
        }

        return back()->with('success', 'Karen updated.');
    }

    public function destroy(Request $request, Karen $karen)
    {
        $this->authorize('delete', $karen);

        $karen->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Karen removed.');
    }

    private function validate(Request $request): array
    {
        return $request->validate([
            'first_name'   => 'nullable|string|max:255',
            'last_name'    => 'nullable|string|max:255',
            'email'        => 'nullable|email|max:255',
            'notes'        => 'nullable|string|max:5000',
            'flagged_date' => 'nullable|date',
        ]);
    }
}
