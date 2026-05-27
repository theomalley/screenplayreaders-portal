<?php

// v1.0 — 2026-05-26 | Create/delete announcements (admin/editor); mark-read/dismiss (reader).

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AnnouncementController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $request->validate(['body' => 'required|string|max:2000']);

        Announcement::create([
            'body'       => trim($request->input('body')),
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Announcement posted.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $announcement->delete();

        return back()->with('success', 'Announcement deleted.');
    }

    public function markRead(Announcement $announcement): Response
    {

        AnnouncementRead::updateOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => auth()->id()],
            ['read_at' => now()]
        );

        return response()->noContent();
    }

    public function dismiss(Announcement $announcement): Response
    {

        AnnouncementRead::updateOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => auth()->id()],
            ['read_at' => now(), 'dismissed_at' => now()]
        );

        return response()->noContent();
    }
}
