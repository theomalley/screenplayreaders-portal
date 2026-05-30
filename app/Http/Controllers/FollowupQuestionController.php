<?php

// v1.0 — 2026-05-30 | Admin/editor management + reader response for followup questions

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\FollowupQuestion;
use App\Services\HelpScoutService;
use App\Services\ReaderNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FollowupQuestionController extends Controller
{
    /** Admin/editor: edit questions, edited_response, or change status. */
    public function update(Request $request, FollowupQuestion $followup): RedirectResponse
    {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);

        $data = $request->validate([
            'edited_questions' => 'nullable|string|max:5000',
            'edited_response'  => 'nullable|string|max:5000',
            'status'           => 'nullable|in:pending,unanswered,answered,complete',
        ]);

        $wasUnanswered = $followup->status !== FollowupQuestion::STATUS_UNANSWERED;
        $newStatus     = $data['status'] ?? $followup->status;

        if ($newStatus === FollowupQuestion::STATUS_UNANSWERED && $followup->status !== FollowupQuestion::STATUS_UNANSWERED) {
            $data['unanswered_at'] = now();
        }

        $followup->update($data);

        // Fire reader notification when transitioning to unanswered
        if ($wasUnanswered && $followup->fresh()->status === FollowupQuestion::STATUS_UNANSWERED) {
            $this->notifyReader($followup);
        }

        return back()->with('success', 'Followup question updated.');
    }

    /** Admin/editor: mark complete and create HelpScout draft. */
    public function complete(Request $request, FollowupQuestion $followup): RedirectResponse
    {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);

        $followup->update(['status' => FollowupQuestion::STATUS_COMPLETE]);

        $this->createHelpScoutDraft($followup);

        return back()->with('success', 'Followup marked complete. HelpScout draft created.');
    }

    /** Admin/editor: delete a followup question at any status. */
    public function destroy(FollowupQuestion $followup): RedirectResponse
    {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);

        $followup->delete();

        return back()->with('success', 'Followup question deleted.');
    }

    /** Reader: submit their response. */
    public function respond(Request $request, FollowupQuestion $followup): JsonResponse
    {
        $user = auth()->user();
        abort_unless($user->isReader(), 403);
        abort_unless($followup->assignment->assigned_reader_id === $user->id, 403);
        abort_unless($followup->status === FollowupQuestion::STATUS_UNANSWERED, 409);

        $data = $request->validate(['response' => 'required|string|max:10000']);

        $followup->update([
            'reader_response' => $data['response'],
            'status'          => FollowupQuestion::STATUS_ANSWERED,
        ]);

        return response()->json(['status' => 'answered']);
    }

    private function notifyReader(FollowupQuestion $followup): void
    {
        $assignment = $followup->assignment()->with('assignedReader.readerProfile')->first();
        $reader     = $assignment?->assignedReader;
        if (! $reader) return;

        $profile = $reader->readerProfile;

        if ($profile?->email_notify_followup) {
            // Reuse existing mail infrastructure — simple notification mail
            \Illuminate\Support\Facades\Mail::to($reader->email)->send(
                new \App\Mail\FollowupQuestionNotification($followup, $reader)
            );
        }

        // SMS notification handled by future Twilio integration using sms_notify_followup flag
    }

    private function createHelpScoutDraft(FollowupQuestion $followup): void
    {
        $assignment = $followup->assignment;
        if (! $assignment?->helpscout_ticket_number) {
            return;
        }

        $service = app(HelpScoutService::class);

        try {
            $conversationId = $service->findConversationIdByTicketNumber($assignment->helpscout_ticket_number);
            if (! $conversationId) return;

            $typeLabel = match($assignment->assignment_type) {
                'script_coverage' => 'Script Coverage',
                'notes_only'      => 'Notes-Only',
                'deep_dive'       => 'Deep-Dive Dev Notes',
                'short'           => 'Short Script Coverage',
                default           => ucwords(str_replace('_', ' ', $assignment->assignment_type)),
            };

            $reader   = $assignment->assignedReader;
            $initials = $reader?->readerProfile?->initials
                ?? ($reader ? strtoupper(substr($reader->name, 0, 2)) : 'Your reader');

            $response = nl2br(e($followup->responseForCustomer() ?? ''));

            $html = "<p><strong>Your {$typeLabel} reader ({$initials}) has responded to your followup questions:</strong></p>"
                  . "<blockquote style=\"border-left:3px solid #ccc;padding-left:1em;margin:1em 0;color:#555;\">"
                  . $response
                  . "</blockquote>";

            $service->createDraftReply($conversationId, $html);
        } catch (\Throwable $e) {
            Log::error('Followup HelpScout draft failed', [
                'followup_id' => $followup->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
