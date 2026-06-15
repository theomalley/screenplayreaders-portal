<?php

// v1.0 — 2026-06-15 | Per-user Notification History — view, clear individually, or clear all

namespace App\Http\Controllers;

use App\Models\NotificationHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationHistoryController extends Controller
{
    public function index(): View
    {
        $notifications = auth()->user()->notificationHistory()->latest()->get();

        return view('notification-history.index', compact('notifications'));
    }

    public function destroy(NotificationHistory $notification): RedirectResponse
    {
        abort_unless($notification->user_id === auth()->id(), 403);

        $notification->delete();

        return back()->with('success', 'Notification cleared.');
    }

    public function destroyAll(): RedirectResponse
    {
        auth()->user()->notificationHistory()->delete();

        return back()->with('success', 'Notification history cleared.');
    }
}
