<?php

// v1.3 — 2026-09-09 | SECURITY: dismiss()/dismissReply() logged the note/reply's raw body
//                     into the dismisser's own Notification History regardless of whether
//                     they had any legitimate access to it — combined with those actions
//                     being deliberately open to any authenticated user (see
//                     AssignmentNotePolicy), this let a reader enumerate note/reply IDs and
//                     read other people's private note content into their own feed. Now
//                     redacted unless the dismisser is the note/reply's own author or
//                     admin/editor.
// v1.2 — 2026-07-23 | Authorization moved to AssignmentPolicy::addNote() /
//                     AssignmentNotePolicy::reply() (app/Policies), replacing inline
//                     abort_unless(...) calls. Covered by
//                     tests/Feature/AssignmentNoteControllerTest.php.
// v1.1 — 2026-06-15 | Log dismissed notes/replies to Notification History
// v1.0 — 2026-05-31 | Reader notes on assignments + admin replies; per-user dismissal

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentNote;
use App\Models\AssignmentNoteReply;
use App\Models\NotificationHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AssignmentNoteController extends Controller
{
    public function store(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->authorize('addNote', $assignment);
        $user = auth()->user();

        $request->validate(['body' => 'required|string|max:1000']);

        $existing = AssignmentNote::where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->count();

        abort_if($existing >= 3, 422, 'Maximum 3 notes per assignment.');

        AssignmentNote::create([
            'assignment_id' => $assignment->id,
            'user_id'       => $user->id,
            'body'          => trim($request->input('body')),
            'dismissed_by'  => [],
        ]);

        return back()->with('success', 'Note sent to editor.');
    }

    public function reply(Request $request, AssignmentNote $note): RedirectResponse
    {
        $this->authorize('reply', $note);

        $request->validate(['body' => 'required|string|max:1000']);

        AssignmentNoteReply::create([
            'assignment_note_id' => $note->id,
            'user_id'            => auth()->id(),
            'body'               => trim($request->input('body')),
            'dismissed_by'       => [],
        ]);

        return back()->with('success', 'Reply sent.');
    }

    public function dismiss(AssignmentNote $note): JsonResponse
    {
        $note->dismiss(auth()->id());

        // SECURITY: dismiss() is deliberately open to any authenticated user (see
        // AssignmentNotePolicy's changelog) — the dismissal itself is harmless. But
        // logging the note's raw body into the *dismisser's own* Notification History
        // let anyone enumerate note IDs and read private reader<->editor content into
        // their own feed even though they have no other access to it. Only log the
        // real body when the dismisser could already legitimately see it (the note's
        // own author, or admin/editor).
        $user    = auth()->user();
        $canView = $user->id === $note->user_id || $user->isAdminOrEditor();

        NotificationHistory::log(
            auth()->id(),
            "Dismissed note — Order #{$note->assignment->order_number}",
            $canView ? $note->body : '(content hidden)',
            route('assignments.edit', $note->assignment_id)
        );

        return response()->json(['status' => 'ok']);
    }

    public function dismissReply(AssignmentNoteReply $reply): JsonResponse
    {
        $reply->dismiss(auth()->id());

        // SECURITY: same fix as dismiss() above — only log the real reply body when
        // the dismisser is the reply's author, the original note's author, or admin/editor.
        $user    = auth()->user();
        $canView = $user->id === $reply->user_id || $user->id === $reply->note->user_id || $user->isAdminOrEditor();

        NotificationHistory::log(
            auth()->id(),
            "Dismissed reply — {$reply->note->assignment->script_title}",
            $canView ? $reply->body : '(content hidden)',
            route('assignments.index')
        );

        return response()->json(['status' => 'ok']);
    }
}
