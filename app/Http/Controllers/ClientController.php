<?php

// v1.4 — 2026-07-23 | Authorization moved to ClientPolicy (app/Policies), replacing
//                     inline abort_unless(...) calls. Covered by
//                     tests/Feature/ClientControllerTest.php.
// v1.3 — 2026-07-07 | Restore batch_invoicing field to validate()
// v1.2 — 2026-06-02 | Remove batch invoicing — clean up show() and validate()
// v1.0 — 2026-05-26 | Client management — CRUD for invoicing clients

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Setting;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Client::class);

        $clients = Client::orderBy('name')->get();

        return view('clients.index', compact('clients'));
    }

    public function create()
    {
        $this->authorize('create', Client::class);

        $defaultSrAddress = Setting::getValue('sr_invoice_address', '');

        return view('clients.form', [
            'client'           => null,
            'defaultSrAddress' => $defaultSrAddress,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Client::class);

        $data = $this->validate($request);

        Client::create($data);

        return redirect()->route('clients.index')->with('success', 'Client created.');
    }

    public function show(Client $client)
    {
        $this->authorize('view', $client);

        return view('clients.show', compact('client'));
    }

    public function edit(Client $client)
    {
        $this->authorize('update', $client);

        $defaultSrAddress = Setting::getValue('sr_invoice_address', '');

        return view('clients.form', [
            'client'           => $client,
            'defaultSrAddress' => $defaultSrAddress,
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $this->authorize('update', $client);

        $data = $this->validate($request, $client);

        $client->update($data);

        return redirect()->route('clients.show', $client)->with('success', 'Client updated.');
    }

    public function destroy(Client $client)
    {
        $this->authorize('delete', $client);

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
