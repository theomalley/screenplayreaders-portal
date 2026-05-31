<?php

// v1.0 — 2026-05-30 | Admin/editor management + reader response for followup questions

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\FollowupQuestion;
use App\Models\FollowupToken;
use App\Models\Setting;
use App\Services\HelpScoutService;
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

        $followup->update(['status' => FollowupQuestion::STATUS_COMPLETE, 'completed_at' => now()]);

        $drafted = $this->createHelpScoutDraft($followup);

        $message = match(true) {
            $drafted === true       => 'Followup marked complete. HelpScout draft created.',
            $drafted === 'no_ticket'=> 'Followup marked complete. No HelpScout ticket number on this assignment.',
            $drafted === 'not_found'=> 'Followup marked complete. HelpScout ticket not found — check the ticket number.',
            default                 => 'Followup marked complete. HelpScout draft failed: ' . $drafted,
        };

        return back()->with('success', $message);
    }

    /** Admin only: delete an entire followup round (token + all its questions). */
    public function destroyToken(FollowupToken $followupToken): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $orderNumber = $followupToken->order_number;
        $followupToken->followupQuestions()->delete();
        $followupToken->delete();

        return redirect()->route('followups.history', $orderNumber)
            ->with('success', 'Followup round deleted.');
    }

    /** Admin/editor: full followup history for an order number. */
    public function history(string $orderNumber): \Illuminate\View\View
    {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);

        $appTimezone = Setting::getAppTimezone();

        // All tokens for this order, oldest first (each = one round of followups)
        $tokens = FollowupToken::where('order_number', $orderNumber)
            ->with(['followupQuestions.assignment.assignedReader.readerProfile'])
            ->orderBy('created_at', 'asc')
            ->get();

        abort_if($tokens->isEmpty(), 404);

        // Grab the script title / writer from any associated assignment
        $representative = Assignment::where('order_number', $orderNumber)->first();

        return view('followup.history', compact('orderNumber', 'tokens', 'appTimezone', 'representative'));
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
            'answered_at'     => now(),
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

    private function createHelpScoutDraft(FollowupQuestion $followup): bool|string
    {
        $assignment = $followup->assignment;
        if (! $assignment) {
            return 'no_assignment';
        }

        $service = app(HelpScoutService::class);

        try {
            // Prefer the Zapier-populated conversation ID (auto-set for all SR orders).
            // Fall back to searching by the manually-entered ticket number.
            $conversationId = $assignment->helpscoutConversation?->helpscout_conversation_id
                ?? null;

            if (! $conversationId && $assignment->helpscout_ticket_number) {
                $conversationId = $service->findConversationIdByTicketNumber($assignment->helpscout_ticket_number);
            }

            if (! $conversationId) return 'not_found';

            $reader      = $assignment->assignedReader;
            $initials    = $reader?->readerProfile?->initials
                ?? ($reader ? strtoupper(substr($reader->name, 0, 2)) : 'Your reader');
            $scriptTitle = $assignment->script_title ?? 'your script';

            $response = nl2br(e($followup->responseForCustomer() ?? ''));

            $html = "<p><strong>Your Reader {$initials} for {$scriptTitle} has responded to your followup questions:</strong></p>"
                  . "<blockquote style=\"border-left:3px solid #ccc;padding-left:1em;margin:1em 0;color:#555;\">"
                  . $response
                  . "</blockquote>";

            $service->createDraftReply($conversationId, $html);
            return true;
        } catch (\Throwable $e) {
            Log::error('Followup HelpScout draft failed', [
                'followup_id'   => $followup->id,
                'ticket_number' => $assignment->helpscout_ticket_number ?? 'none',
                'error'         => $e->getMessage(),
            ]);
            return $e->getMessage();
        }
    }
}
