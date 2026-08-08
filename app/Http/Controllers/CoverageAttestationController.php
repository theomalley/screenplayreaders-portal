<?php

// v1.0 — 2026-08-08 | Admin CRUD + reordering for SR coverage form quality attestation checkboxes

namespace App\Http\Controllers;

use App\Models\CoverageAttestation;
use Illuminate\Http\Request;

class CoverageAttestationController extends Controller
{
    public function index()
    {
        $attestations = CoverageAttestation::orderBy('sort_order')->orderBy('id')->get();

        return view('admin.coverage-attestations.index', compact('attestations'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:500'],
        ]);

        CoverageAttestation::create([
            'text'       => $data['text'],
            'sort_order' => (CoverageAttestation::max('sort_order') ?? 0) + 1,
        ]);

        return back()->with('success', 'Attestation added.');
    }

    public function update(Request $request, CoverageAttestation $coverageAttestation)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:500'],
        ]);

        $coverageAttestation->update($data);

        return back()->with('success', 'Attestation updated.');
    }

    public function destroy(CoverageAttestation $coverageAttestation)
    {
        $coverageAttestation->delete();

        return back()->with('success', 'Attestation deleted.');
    }

    public function moveUp(CoverageAttestation $coverageAttestation)
    {
        $prev = CoverageAttestation::where('sort_order', '<', $coverageAttestation->sort_order)
            ->orderBy('sort_order', 'desc')
            ->first();

        if ($prev) {
            [$a, $b] = [$coverageAttestation->sort_order, $prev->sort_order];
            $coverageAttestation->update(['sort_order' => $b]);
            $prev->update(['sort_order' => $a]);
        }

        return back();
    }

    public function moveDown(CoverageAttestation $coverageAttestation)
    {
        $next = CoverageAttestation::where('sort_order', '>', $coverageAttestation->sort_order)
            ->orderBy('sort_order', 'asc')
            ->first();

        if ($next) {
            [$a, $b] = [$coverageAttestation->sort_order, $next->sort_order];
            $coverageAttestation->update(['sort_order' => $b]);
            $next->update(['sort_order' => $a]);
        }

        return back();
    }
}
