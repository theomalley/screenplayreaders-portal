<?php

// v1.0 — 2026-05-26 | Invoice creation, status management, and standalone invoicing tab

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    /**
     * Invoicing tab — select client and create a standalone invoice.
     */
    public function index()
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $clients = Client::orderBy('name')->get();

        return view('invoicing.index', compact('clients'));
    }

    /**
     * Create a standalone invoice from the Invoicing tab (no assignment).
     */
    public function store(Request $request)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

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

        return redirect()->route('clients.show', $client)
            ->with('success', "Invoice #{$invoice->invoice_number} created and sent.");
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

        return back()->with('success', "Invoice #{$invoice->invoice_number} marked as paid.");
    }

    /**
     * Void an invoice (and its Stripe invoice if applicable).
     */
    public function void(Invoice $invoice)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        if ($invoice->stripe_invoice_id) {
            try {
                app(InvoiceService::class);
                (new \App\Services\StripeService())->voidInvoice($invoice->stripe_invoice_id);
            } catch (\Throwable $e) {
                return back()->withErrors(['invoice' => 'Stripe void failed: ' . $e->getMessage()]);
            }
        }

        $invoice->update(['status' => 'void']);

        return back()->with('success', "Invoice #{$invoice->invoice_number} voided.");
    }
}
