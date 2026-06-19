<?php

// v1.3 — 2026-06-19 | Use CompletionDraftService; catch MissingHelpScoutConversationException;
//                     bulk sendToQc update; prefetch credit packages to eliminate N+1
// v1.2 — 2026-06-19 | redraftGoback() accepts optional ticket_number param — prompts inline when HS conversation is missing
// v1.1 — 2026-06-19 | sendToQc() — return completed order to QC; redraftGoback() — recreate HelpScout draft from archive

namespace App\Http\Controllers;

use App\Exceptions\MissingHelpScoutConversationException;
use App\Models\Assignment;
use App\Models\FollowupQuestion;
use App\Models\ReadCreditPackage;
use App\Services\CompletionDraftService;
use App\Services\GoogleDocsService;
use App\Support\FilenameGenerator;
use App\Support\Permission;
use Illuminate\Support\Facades\Log;

class ArchiveController extends Controller
{
    public function index()
    {
        abort_unless(Permission::check('archive'), 403);

        $groups = Assignment::with(['assignedReader.readerProfile', 'coverageSubmission', 'helpscoutConversation'])
            ->where('status', Assignment::STATUS_COMPLETED)
            ->get()
            ->groupBy('order_number')
            ->sortByDesc(fn($group) => $group->max(fn($a) => $a->completed_at?->timestamp ?? 0));

        $ordersWithSubmissions = FollowupQuestion::whereIn('order_number', $groups->keys())
            ->pluck('order_number')
            ->unique()
            ->flip()
            ->all();

        // Prefetch credit packages for CREDIT- orders to avoid N+1 queries in the view
        $allOrderNumbers = $groups->keys()->merge(
            Assignment::where('status', Assignment::STATUS_CANCELLED)->pluck('order_number')
        );
        $creditWooOrders = $allOrderNumbers
            ->filter(fn($o) => str_starts_with($o, 'CREDIT-'))
            ->map(fn($o) => preg_match('/^CREDIT-(.+)-\d+$/', $o, $m) ? $m[1] : null)
            ->filter()
            ->unique();
        $creditPackages = $creditWooOrders->isNotEmpty()
            ? ReadCreditPackage::whereIn('woo_order_number', $creditWooOrders)->get()->keyBy('woo_order_number')
            : collect();

        $cancelled = Assignment::with(['assignedReader.readerProfile'])
            ->where('status', Assignment::STATUS_CANCELLED)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('order_number');

        return view('archive.index', compact('groups', 'ordersWithSubmissions', 'cancelled', 'creditPackages'));
    }

    public function sendToQc(Assignment $assignment)
    {
        abort_unless(Permission::check('archive'), 403);
        abort_unless($assignment->status === Assignment::STATUS_COMPLETED, 422);

        Assignment::where('order_number', $assignment->order_number)
            ->where('status', Assignment::STATUS_COMPLETED)
            ->update([
                'status'                       => Assignment::STATUS_QC,
                'completed_at'                 => null,
                'helpscout_draft_sent_at'      => null,
                'helpscout_draft_dismissed_by' => null,
            ]);

        return redirect()->route('archive.index')
            ->with('success', "#{$assignment->order_number} — {$assignment->script_title} sent back to QC.");
    }

    public function redraftGoback(Assignment $assignment)
    {
        abort_unless(Permission::check('archive'), 403);
        abort_unless($assignment->status === Assignment::STATUS_COMPLETED, 422);

        $isAjax      = request()->expectsJson();
        $ticketInput = trim((string) request()->input('ticket_number', ''));

        $siblings = Assignment::where('order_number', $assignment->order_number)
            ->with(['assignedReader.readerProfile'])
            ->where('status', Assignment::STATUS_COMPLETED)
            ->whereNotNull('drive_coverage_doc_id')
            ->get();

        if ($siblings->isEmpty()) {
            $msg = 'No coverage docs found for this order.';
            return $isAjax ? response()->json(['error' => $msg], 422) : back()->with('error', $msg);
        }

        $docs = new GoogleDocsService();
        foreach ($siblings as $sibling) {
            if (! $sibling->drive_coverage_pdf_id) {
                try {
                    $initials = $sibling->assignedReader?->readerProfile?->initials;
                    $pdfId    = $docs->exportToPdf(
                        $sibling->drive_coverage_doc_id,
                        FilenameGenerator::coverageDoc($sibling, $initials)
                    );
                    $sibling->update(['drive_coverage_pdf_id' => $pdfId]);
                    $sibling->refresh();
                } catch (\Throwable $e) {
                    Log::error('Archive redraft PDF generation failed', [
                        'assignment_id' => $sibling->id,
                        'error'         => $e->getMessage(),
                    ]);
                }
            }
        }

        try {
            $hsUrl = app(CompletionDraftService::class)->buildDraft($siblings->all(), $ticketInput ?: null);

            if ($isAjax) {
                return response()->json(['url' => $hsUrl]);
            }

            return CompletionDraftService::openInNewTab($hsUrl, route('archive.index'));
        } catch (MissingHelpScoutConversationException $e) {
            if ($isAjax) {
                return response()->json(['error' => $e->getMessage(), 'needs_ticket' => true], 422);
            }

            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Archive redraft HelpScout draft failed', [
                'order_number' => $assignment->order_number,
                'error'        => $e->getMessage(),
            ]);

            $msg = 'HelpScout draft could not be created: ' . $e->getMessage();
            return $isAjax ? response()->json(['error' => $msg], 500) : back()->with('error', $msg);
        }
    }
}
