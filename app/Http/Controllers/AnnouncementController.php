<?php

// v1.2 — 2026-06-14 | Add update() — admins can edit any announcement, editors only their own
// v1.1 — 2026-06-02 | Add expires_at support to store(); add history() for all-user announcement archive
// v1.0 — 2026-05-26 | Create/delete announcements (admin/editor); mark-read/dismiss (reader).

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AnnouncementController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $request->validate([
            'body'       => 'required|string|max:2000',
            'expires_at' => 'nullable|date_format:Y-m-d\TH:i',
        ]);

        $expiresAt = null;
        if ($request->filled('expires_at')) {
            $expiresAt = Carbon::createFromFormat('Y-m-d\TH:i', $request->input('expires_at'), Setting::getAppTimezone());
        }

        Announcement::create([
            'body'       => trim($request->input('body')),
            'expires_at' => $expiresAt,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Announcement posted.');
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($announcement->canBeEditedBy(auth()->user()), 403);

        $request->validate([
            'body'       => 'required|string|max:2000',
            'expires_at' => 'nullable|date_format:Y-m-d\TH:i',
        ]);

        $expiresAt = null;
        if ($request->filled('expires_at')) {
            $expiresAt = Carbon::createFromFormat('Y-m-d\TH:i', $request->input('expires_at'), Setting::getAppTimezone());
        }

        $announcement->update([
            'body'       => trim($request->input('body')),
            'expires_at' => $expiresAt,
        ]);

        return back()->with('success', 'Announcement updated.');
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

    /** Paginated history of all announcements — visible to all authenticated users. */
    public function history(Request $request)
    {
        $userId        = auth()->id();
        $appTimezone   = Setting::getAppTimezone();

        $announcements = Announcement::with(['createdBy', 'reads' => fn($q) => $q->where('user_id', $userId)])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('announcements.history', compact('announcements', 'appTimezone'));
    }
}
