<?php

// v1.1 — 2026-06-02 | draftEmail — creates a HelpScout draft to a reader/editor and redirects to it
// v1.0 — 2026-05-31 | Staff icon popup card — returns rendered HTML for any admin/editor context

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Setting;
use App\Models\User;
use App\Services\HelpScoutService;
use App\Support\PayPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class StaffCardController extends Controller
{
    public function card(User $user): Response
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $user->loadMissing([
            'assignments' => fn($q) => $q->whereIn('status', [
                Assignment::STATUS_INCOMING,
                Assignment::STATUS_UNASSIGNED,
                Assignment::STATUS_ASSIGNED,
                Assignment::STATUS_QC,
                'needs_attention',
            ]),
            'readerProfile',
            'editorProfile',
        ]);

        $profile = $user->isReader() ? $user->readerProfile : $user->editorProfile;

        // Weekly pay stats (readers only)
        $weekStats = null;
        if ($user->isReader()) {
            [$thisPeriodStart, $thisPeriodEnd] = PayPeriod::current();
            $lastPeriodStart = PayPeriod::start($thisPeriodStart->copy()->subDay());

            $periodCompleted = Assignment::where('assigned_reader_id', $user->id)
                ->where('status', Assignment::STATUS_COMPLETED)
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', $lastPeriodStart)
                ->get(['completed_at', 'pay_rate']);

            $thisWeek = $periodCompleted->filter(fn($a) => $a->completed_at >= $thisPeriodStart);
            $lastWeek = $periodCompleted->filter(fn($a) => $a->completed_at < $thisPeriodStart);

            $weekStats = [
                'this_count' => $thisWeek->count(),
                'this_pay'   => $thisWeek->sum('pay_rate'),
                'last_count' => $lastWeek->count(),
                'last_pay'   => $lastWeek->sum('pay_rate'),
                'this_label' => PayPeriod::label($thisPeriodStart),
                'last_label' => PayPeriod::label($lastPeriodStart),
            ];
        }

        $appTimezone = Setting::getAppTimezone();
        $viewer      = auth()->user();

        // Edit profile link — admins are not editable via this popup
        $editUrl = null;
        if (! $user->isAdmin()) {
            if ($viewer->isAdmin() || ($viewer->canManageAssignments() && $user->isReader())) {
                $editUrl = $user->isReader()
                    ? route('readers.edit', $user)
                    : route('admin.editors.edit', $user);
            }
        }

        $html = view('partials.staff-card', compact('user', 'profile', 'weekStats', 'appTimezone', 'editUrl'))->render();

        return response($html);
    }

    /**
     * Create a HelpScout draft addressed to a reader or editor and redirect to it.
     * Opens a new outgoing conversation pre-addressed to the user's email.
     */
    public function draftEmail(User $user, HelpScoutService $helpScout): RedirectResponse
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);
        abort_if($user->isAdmin(), 403);

        $user->loadMissing(['readerProfile', 'editorProfile']);
        $name = $user->isReader()
            ? ($user->readerProfile?->displayName() ?? $user->name)
            : ($user->editorProfile?->displayName() ?? $user->name);

        $url = $helpScout->createDirectReaderDraft($user->email, $name);

        return redirect($url);
    }
}
