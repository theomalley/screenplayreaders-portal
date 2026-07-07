<?php

// v2.3 — 2026-07-07 | Add send() — manually send an accumulated batch draft invoice
// v2.2 — 2026-06-03 | Add markOutstanding() — revert a paid PDF invoice back to sent/outstanding
// v2.1 — 2026-06-02 | Add edit(), update(), downloadPdf() — edit/regenerate and download for PDF invoices
// v2.0 — 2026-06-02 | Remove batch invoicing; store() accepts up to 8 line items and sends immediately
// v1.4 — 2026-05-26 | Add customer invoice path (no client record; Stripe + optional HelpScout draft)
// v1.0 — 2026-05-26 | Invoice creation, status management, and standalone invoicing tab

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\OrderRevenue;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    /**
     * Invoicing tab — all outstanding and paid invoices across all clients.
     */
    public function index()
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $outstanding = Invoice::with('client')
            ->whereIn('status', ['draft', 'sent'])
            ->orderByDesc('issued_at')
            ->get();

        $paid = Invoice::with('client')
            ->where('status', 'paid')
            ->orderByDesc('paid_at')
            ->get();

        return view('invoicing.index', compact('outstanding', 'paid'));
    }

    /**
     * Show the standalone create-invoice form.
     */
    public function create()
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $clients = Client::orderBy('name')->get();

        return view('invoicing.create', compact('clients'));
    }

    /**
     * Create and immediately send an invoice from the Invoicing tab.
     * Accepts up to 8 line items. Branches on recipient_type:
     *   'customer' — Stripe-only, no client record
     *   'client'   — existing client workflow (Stripe or PDF)
     */
    public function store(Request $request)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        if ($request->input('recipient_type') === 'customer') {
            return $this->storeForCustomer($request);
        }

        $data = $request->validate([
            'client_id'                  => 'required|exists:clients,id',
            'items'                      => 'required|array|min:1|max:8',
            'items.*.description'        => 'required|string|max:1000',
            'items.*.amount'             => 'required|numeric|min:0.01',
            'due_date'                   => 'nullable|date',
            'notes'                      => 'nullable|string|max:2000',
        ]);

        $client    = Client::findOrFail($data['client_id']);
        $lineItems = array_map(fn ($item) => [
            'description' => $item['description'],
            'amount'      => (float) $item['amount'],
        ], $data['items']);

        try {
            $invoice = $this->invoiceService->generate(
                client:     $client,
                lineItems:  $lineItems,
                assignment: null,
                notes:      $data['notes'] ?? null,
                dueDate:    $data['due_date'] ?? null,
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['invoice' => $e->getMessage()])->withInput();
        }

        return redirect()->route('invoicing.index')
            ->with('success', "Invoice #{$invoice->invoice_number} created and sent.");
    }

    private function storeForCustomer(Request $request)
    {
        $data = $request->validate([
            'customer_first_name'        => 'required|string|max:100',
            'customer_last_name'         => 'required|string|max:100',
            'customer_email'             => 'required|email|max:255',
            'helpscout_ticket'           => 'nullable|string|max:50',
            'items'                      => 'required|array|min:1|max:8',
            'items.*.description'        => 'required|string|max:1000',
            'items.*.amount'             => 'required|numeric|min:0.01',
            'due_date'                   => 'nullable|date',
        ]);

        $lineItems = array_map(fn ($item) => [
            'description' => $item['description'],
            'amount'      => (float) $item['amount'],
        ], $data['items']);

        try {
            $invoice = $this->invoiceService->generateForCustomer(
                email:           $data['customer_email'],
                firstName:       $data['customer_first_name'],
                lastName:        $data['customer_last_name'],
                lineItems:       $lineItems,
                helpscoutTicket: $data['helpscout_ticket'] ?? null,
                dueDate:         $data['due_date'] ?? null,
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['invoice' => $e->getMessage()])->withInput();
        }

        return redirect()->route('invoicing.index')
            ->with('success', "Invoice {$invoice->invoice_number} sent to {$data['customer_email']}.");
    }

    /**
     * Create an invoice triggered from an assignment form.
     * Called by AssignmentController after the assignment is saved.
     */
    public function storeFromAssignment(Assignment $assignment, float $amount): Invoice
    {
        $client = $assignment->client;

        if (! $client) {
            throw new \RuntimeException('Assignment has no client set.');
        }

        $typeLabel = match ($assignment->assignment_type ?? '') {
            'notes_only' => 'Notes-Only Coverage',
            'deep_dive'  => 'Development Notes',
            'budget'     => 'Budget Coverage',
            'short'      => 'Short Coverage',
            default      => 'Script Coverage',
        };

        if ($assignment->vendor === 'wd') {
            $typeLabel = "Writer's Digest Coverage";
        }

        $description = "{$typeLabel} — {$assignment->script_title} (Order #{$assignment->order_number})";

        return $this->invoiceService->generate(
            client:     $client,
            lineItems:  [['description' => $description, 'amount' => $amount]],
            assignment: $assignment,
        );
    }

    /**
     * Manually send an accumulated batch draft invoice (PDF only).
     */
    public function send(Invoice $invoice)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);
        abort_unless($invoice->status === 'draft', 403);

        try {
            $this->invoiceService->sendBatch($invoice->fresh());
        } catch (\Throwable $e) {
            return back()->withErrors(['invoice' => 'Send failed: ' . $e->getMessage()]);
        }

        return redirect()->route('invoicing.index')
            ->with('success', "Invoice #{$invoice->invoice_number} sent.");
    }

    /**
     * Mark an invoice as paid.
     */
    public function markPaid(Invoice $invoice)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $invoice->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        if (! $invoice->client_id) {
            return redirect()->route('invoicing.index')
                ->with('success', "Invoice #{$invoice->invoice_number} marked as paid.");
        }

        try {
            $this->invoiceService->logToOrderRevenue($invoice->fresh());
        } catch (\Throwable $e) {
            Log::error('markPaid: logToOrderRevenue failed', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);
            return redirect()->route('invoicing.index')
                ->with('success', "Invoice #{$invoice->invoice_number} marked as paid.")
                ->withErrors(['invoice' => 'Order log sync failed: ' . $e->getMessage()]);
        }

        return redirect()->route('invoicing.index')
            ->with('success', "Invoice #{$invoice->invoice_number} marked as paid.");
    }

    /**
     * Delete a paid invoice and remove it from the order log (client invoices only).
     */
    public function destroy(Invoice $invoice)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        if ($invoice->status !== 'paid') {
            return back()->withErrors(['invoice' => 'Only paid invoices can be deleted.']);
        }

        $invoiceNumber = $invoice->invoice_number;

        if ($invoice->client_id) {
            $code = strtoupper($invoice->client->code ?? 'INV');
            OrderRevenue::where('order_number', "INV-{$code}-{$invoiceNumber}")->delete();
        }

        $invoice->delete();

        return redirect()->route('invoicing.index')
            ->with('success', "Invoice #{$invoiceNumber} deleted.");
    }

    /**
     * Show the edit form for a PDF invoice.
     */
    public function edit(Invoice $invoice)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);
        abort_unless($invoice->invoice_type === 'pdf', 403);

        $lineItems = $invoice->lineItems()->orderBy('created_at')->get();

        return view('invoicing.edit', compact('invoice', 'lineItems'));
    }

    /**
     * Update a PDF invoice's line items and regenerate the PDF.
     * Does not re-send the email — admin can download the updated PDF.
     */
    public function update(Request $request, Invoice $invoice)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);
        abort_unless($invoice->invoice_type === 'pdf', 403);

        $data = $request->validate([
            'items'               => 'required|array|min:1|max:8',
            'items.*.description' => 'required|string|max:1000',
            'items.*.amount'      => 'required|numeric|min:0.01',
            'due_date'            => 'nullable|date',
            'notes'               => 'nullable|string|max:2000',
        ]);

        $lineItems = array_map(fn ($i) => [
            'description' => $i['description'],
            'amount'      => (float) $i['amount'],
        ], $data['items']);

        $total       = array_sum(array_column($lineItems, 'amount'));
        $description = $lineItems[0]['description'];

        // Replace all line items
        $invoice->lineItems()->delete();
        foreach ($lineItems as $item) {
            \App\Models\InvoiceLineItem::create([
                'invoice_id'  => $invoice->id,
                'description' => $item['description'],
                'amount'      => $item['amount'],
            ]);
        }

        $invoice->update([
            'description' => $description,
            'amount'      => $total,
            'due_date'    => $data['due_date'] ?? $invoice->due_date,
            'notes'       => $data['notes'] ?? $invoice->notes,
        ]);

        // Regenerate the Google Doc / PDF
        try {
            $client = $invoice->client;
            $this->invoiceService->regeneratePdf($invoice->fresh(), $client, $lineItems);
        } catch (\Throwable $e) {
            return back()->withErrors(['invoice' => 'Line items saved but PDF regeneration failed: ' . $e->getMessage()]);
        }

        return redirect()->route('invoicing.index')
            ->with('success', "Invoice #{$invoice->invoice_number} updated and PDF regenerated.");
    }

    /**
     * Resend a PDF invoice to the client via MailerSend.
     */
    public function resend(Invoice $invoice)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);
        abort_unless($invoice->invoice_type === 'pdf' && $invoice->google_doc_id, 403);

        try {
            $docs     = app(\App\Services\GoogleDocsService::class);
            $bytes    = $docs->exportDocToPdfBytes($invoice->google_doc_id);
            $filename = 'Invoice #' . $invoice->invoice_number
                . ($invoice->client ? ' — ' . $invoice->client->name : '');
            $this->invoiceService->sendPdfByMailerSend($invoice, $invoice->client, $bytes, $filename);
        } catch (\Throwable $e) {
            return back()->withErrors(['invoice' => 'Resend failed: ' . $e->getMessage()]);
        }

        return back()->with('success', "Invoice #{$invoice->invoice_number} resent.");
    }

    /**
     * Stream the PDF for a PDF invoice directly from Google Drive.
     */
    public function downloadPdf(Invoice $invoice)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);
        abort_unless($invoice->invoice_type === 'pdf' && $invoice->google_doc_id, 404);

        try {
            $docs     = app(\App\Services\GoogleDocsService::class);
            $bytes    = $docs->exportDocToPdfBytes($invoice->google_doc_id);
            $filename = 'Invoice #' . $invoice->invoice_number
                . ($invoice->client ? ' — ' . $invoice->client->name : '')
                . '.pdf';
        } catch (\Throwable $e) {
            return back()->withErrors(['invoice' => 'Could not retrieve PDF: ' . $e->getMessage()]);
        }

        $inline      = request()->boolean('view');
        $disposition = $inline ? 'inline' : 'attachment';

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
            'Content-Length'      => strlen($bytes),
        ]);
    }

    /**
     * Revert a paid PDF invoice back to outstanding (sent).
     * Removes the order revenue entry that was created when it was marked paid.
     */
    public function markOutstanding(Invoice $invoice)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);
        abort_unless($invoice->invoice_type === 'pdf', 403);

        if (! $invoice->isPaid()) {
            return back()->withErrors(['invoice' => 'Invoice is not currently marked as paid.']);
        }

        if ($invoice->client_id) {
            $code = strtoupper($invoice->client->code ?? 'INV');
            OrderRevenue::where('order_number', "INV-{$code}-{$invoice->invoice_number}")->delete();
        }

        $invoice->update([
            'status'  => 'sent',
            'paid_at' => null,
        ]);

        return redirect()->route('invoices.edit', $invoice)
            ->with('success', "Invoice #{$invoice->invoice_number} marked as outstanding.");
    }

    /**
     * Void an invoice (and its Stripe invoice if applicable).
     */
    public function void(Invoice $invoice)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        if ($invoice->stripe_invoice_id) {
            try {
                (new \App\Services\StripeService())->voidInvoice($invoice->stripe_invoice_id);
            } catch (\Throwable $e) {
                return back()->withErrors(['invoice' => 'Stripe void failed: ' . $e->getMessage()]);
            }
        }

        $invoice->update(['status' => 'void']);

        return back()->with('success', "Invoice #{$invoice->invoice_number} voided.");
    }
}
