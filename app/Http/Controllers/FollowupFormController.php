<?php

// v1.0 — 2026-05-30 | Public customer followup question form (no auth required)

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\FollowupQuestion;
use App\Models\FollowupToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowupFormController extends Controller
{
    public function show(string $token): View
    {
        $followupToken = FollowupToken::where('token', $token)->firstOrFail();

        abort_if($followupToken->isExpired(), 410, 'This followup link has expired.');

        $slots = $this->buildSlots($followupToken);

        return view('followup.form', compact('followupToken', 'slots', 'token'));
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $followupToken = FollowupToken::where('token', $token)->firstOrFail();

        abort_if($followupToken->isExpired(), 410, 'This followup link has expired.');

        $request->validate([
            'questions'   => 'required|array',
            'questions.*' => 'nullable|string|max:3000',
        ]);

        foreach ($request->input('questions', []) as $assignmentId => $questions) {
            $assignmentId = (int) $assignmentId;

            if (! in_array($assignmentId, $followupToken->assignment_ids)) {
                continue;
            }

            $existing = FollowupQuestion::where('followup_token_id', $followupToken->id)
                ->where('assignment_id', $assignmentId)
                ->first();

            // Only create/update if still pending (lock once unanswered or beyond)
            if ($existing && ! $existing->isEditable()) {
                continue;
            }

            $trimmed = trim($questions ?? '');
            if ($trimmed === '') {
                continue;
            }

            FollowupQuestion::updateOrCreate(
                ['followup_token_id' => $followupToken->id, 'assignment_id' => $assignmentId],
                [
                    'order_number'       => $followupToken->order_number,
                    'customer_questions' => $trimmed,
                    'status'             => FollowupQuestion::STATUS_PENDING,
                ]
            );
        }

        return redirect()->route('followup.show', $token)
            ->with('submitted', true);
    }

    /** Build slot data: assignment info + existing followup question (if any). */
    private function buildSlots(FollowupToken $token): array
    {
        $assignments = Assignment::with('assignedReader.readerProfile')
            ->whereIn('id', $token->assignment_ids)
            ->get()
            ->keyBy('id');

        $questions = FollowupQuestion::where('followup_token_id', $token->id)
            ->get()
            ->keyBy('assignment_id');

        $slots = [];
        foreach ($token->assignment_ids as $id) {
            $assignment = $assignments->get($id);
            if (! $assignment) {
                continue;
            }

            $reader   = $assignment->assignedReader;
            $initials = $reader?->readerProfile?->initials
                ?? ($reader ? strtoupper(substr($reader->name, 0, 2)) : '??');

            $typeLabel = match($assignment->assignment_type) {
                'script_coverage'   => 'Script Coverage',
                'notes_only'        => 'Notes-Only',
                'deep_dive'         => 'Deep-Dive Dev Notes',
                'short'             => 'Short Script Coverage',
                'budget'            => 'Budget Coverage',
                default             => ucwords(str_replace('_', ' ', $assignment->assignment_type)),
            };

            $fq = $questions->get($id);

            $slots[] = [
                'assignment_id' => $id,
                'type_label'    => $typeLabel,
                'initials'      => $initials,
                'followup'      => $fq,
                'locked'        => $fq && ! $fq->isEditable(),
                'existing_text' => $fq?->customer_questions ?? '',
            ];
        }

        return $slots;
    }
}
