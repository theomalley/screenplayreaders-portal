<?php

// v1.1 — 2026-05-26 | Add batch_invoicing field; pass open batch draft to show view
// v1.0 — 2026-05-26 | Client management — CRUD for invoicing clients

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Setting;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $clients = Client::orderBy('name')->get();

        return view('clients.index', compact('clients'));
    }

    public function create()
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $defaultSrAddress = Setting::getValue('sr_invoice_address', '');

        return view('clients.form', [
            'client'           => null,
            'defaultSrAddress' => $defaultSrAddress,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $data = $this->validate($request);

        Client::create($data);

        return redirect()->route('clients.index')->with('success', 'Client created.');
    }

    public function show(Client $client)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        // For batch clients, find the open accumulating draft
        $batchDraft = null;
        if ($client->batch_invoicing) {
            $batchDraft = $client->invoices()
                ->where('status', 'draft')
                ->whereNull('stripe_invoice_id')
                ->whereNull('google_doc_id')
                ->with('lineItems.assignment')
                ->latest()
                ->first();
        }

        return view('clients.show', compact('client', 'batchDraft'));
    }

    public function edit(Client $client)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $defaultSrAddress = Setting::getValue('sr_invoice_address', '');

        return view('clients.form', [
            'client'           => $client,
            'defaultSrAddress' => $defaultSrAddress,
        ]);
    }

    public function update(Request $request, Client $client)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $data = $this->validate($request, $client);

        $client->update($data);

        return redirect()->route('clients.show', $client)->with('success', 'Client updated.');
    }

    public function destroy(Client $client)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Client deleted.');
    }

    private function validate(Request $request, ?Client $client = null): array
    {
        $uniqueCode = 'required|string|max:50|alpha_num|unique:clients,code' . ($client ? ",{$client->id}" : '');

        return $request->validate([
            'name'                => 'required|string|max:255',
            'code'                => $uniqueCode,
            'sr_address'          => 'nullable|string|max:1000',
            'address_line1'       => 'nullable|string|max:255',
            'address_line2'       => 'nullable|string|max:255',
            'city'                => 'nullable|string|max:100',
            'state'               => 'nullable|string|max:100',
            'postcode'            => 'nullable|string|max:20',
            'country'             => 'nullable|string|max:100',
            'email'               => 'nullable|email|max:255',
            'notes'               => 'nullable|string',
            'last_invoice_number' => 'required|integer|min:0',
            'invoice_type'        => 'required|in:pdf,stripe',
            'batch_invoicing'     => 'boolean',
        ]);
    }
}
