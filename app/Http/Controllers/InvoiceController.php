<?php

// v1.4 — 2026-05-26 | Add customer invoice path (no client record; Stripe + optional HelpScout draft)
// v1.3 — 2026-05-26 | Surface logToOrderRevenue errors instead of silently swallowing them
// v1.2 — 2026-05-26 | Split index (all invoices) from create (standalone form)
// v1.1 — 2026-05-26 | Add send() for batch draft invoices
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
     * Create a standalone invoice from the Invoicing tab.
     * Branches on recipient_type: 'customer' (Stripe-only, no client record)
     * or 'client' (existing client workflow).
     */
    public function store(Request $request)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        if ($request->input('recipient_type') === 'customer') {
            return $this->storeForCustomer($request);
        }

        $data = $request->validate([
            'client_id'   => 'required|exists:clients,id',
            'description' => 'required|string|max:1000',
            'amount'      => 'required|numeric|min:0.01',
            'due_date'    => 'nullable|date',
            'notes'       => 'nullable|string|max:2000',
        ]);

        $client = Client::findOrFail($data['client_id']);

        try {
            $invoice = $this->invoiceService->generate(
                client:      $client,
                description: $data['description'],
                amount:      (float) $data['amount'],
                assignment:  null,
                notes:       $data['notes'] ?? null,
                dueDate:     $data['due_date'] ?? null,
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
            'customer_first_name' => 'required|string|max:100',
            'customer_last_name'  => 'required|string|max:100',
            'customer_email'      => 'required|email|max:255',
            'helpscout_ticket'    => 'nullable|string|max:50',
            'description'         => 'required|string|max:1000',
            'amount'              => 'required|numeric|min:0.01',
            'due_date'            => 'nullable|date',
        ]);

        try {
            $invoice = $this->invoiceService->generateForCustomer(
                email:           $data['customer_email'],
                firstName:       $data['customer_first_name'],
                lastName:        $data['customer_last_name'],
                description:     $data['description'],
                amount:          (float) $data['amount'],
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
            client:      $client,
            description: $description,
            amount:      $amount,
            assignment:  $assignment,
        );
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

        // Customer invoices (no client record) are not logged to the order revenue table
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

        // Only client invoices are logged to order_revenues
        if ($invoice->client_id) {
            $code = strtoupper($invoice->client->code ?? 'INV');
            OrderRevenue::where('order_number', "INV-{$code}-{$invoiceNumber}")->delete();
        }

        $invoice->delete();

        return redirect()->route('invoicing.index')
            ->with('success', "Invoice #{$invoiceNumber} deleted.");
    }

    /**
     * Send a batch draft invoice — compiles all line items and fires delivery.
     */
    public function send(Invoice $invoice)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        if ($invoice->status !== 'draft') {
            return back()->withErrors(['invoice' => 'Only draft invoices can be sent.']);
        }

        try {
            $this->invoiceService->send($invoice);
        } catch (\Throwable $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('invoicing.index')
            ->with('success', "Invoice #{$invoice->invoice_number} sent.");
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
