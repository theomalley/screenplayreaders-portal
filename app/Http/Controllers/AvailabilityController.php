<?php

// v1.0 — 2026-05-24 | Reader-facing availability self-service (status + message)

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    private function profile(): ?\Illuminate\Database\Eloquent\Model
    {
        $user = auth()->user();
        return $user->isReader() ? $user->readerProfile : $user->editorProfile;
    }

    public function edit()
    {
        $profile = $this->profile();
        abort_if(! $profile, 404);

        return view('availability.edit', compact('profile'));
    }

    public function update(Request $request)
    {
        $profile = $this->profile();
        abort_if(! $profile, 404);

        $data = $request->validate([
            'availability'         => ['required', 'in:available,unavailable'],
            'availability_message' => ['nullable', 'string', 'max:500'],
        ]);

        $profile->update($data);

        return redirect()->route('profile.edit')->with('availability_success', 'Availability updated.');
    }
}
