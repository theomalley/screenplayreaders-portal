<?php

// v1.1 — 2026-06-22 | Admin adjustment note required; log all credit changes
// v1.0 — 2026-06-18 | Admin/editor views for Notes-Only read credit packages — list, create, edit

namespace App\Http\Controllers;

use App\Models\ReadCreditPackage;
use App\Models\Setting;
use Illuminate\Http\Request;

class ReadCreditController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $q      = trim((string) $request->input('q', ''));
        $status = $request->input('status', 'all');

        $query = ReadCreditPackage::query()->orderByDesc('created_at');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('customer_email', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('woo_order_number', 'like', "%{$q}%");
            });
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $packages = $query->paginate(25)->withQueryString();

        return view('read-credits.index', [
            'packages' => $packages,
            'q'        => $q,
            'status'   => $status,
        ]);
    }

    public function create()
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        return view('read-credits.create');
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $data = $request->validate([
            'customer_email' => 'required|email|max:255',
            'customer_name'  => 'required|string|max:255',
            'credits'        => 'required|integer|min:1|max:999',
            'expires_at'     => 'required|date|after:today',
        ]);

        $pkg = ReadCreditPackage::create([
            'customer_email'    => $data['customer_email'],
            'customer_name'     => $data['customer_name'],
            'woo_order_number'  => 'MANUAL-' . now()->format('YmdHis') . '-' . auth()->id(),
            'product_id'        => 0,
            'credits_purchased' => $data['credits'],
            'credits_remaining' => $data['credits'],
            'status'            => ReadCreditPackage::STATUS_ACTIVE,
            'expires_at'        => $data['expires_at'],
        ]);

        return redirect()->route('read-credits.index')
            ->with('success', "Credit package created for {$pkg->customer_email}. Upload URL: {$pkg->uploadUrl()}");
    }

    public function edit(ReadCreditPackage $package)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $appTimezone = Setting::getAppTimezone();

        return view('read-credits.edit', compact('package', 'appTimezone'));
    }

    public function update(Request $request, ReadCreditPackage $package)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $data = $request->validate([
            'credits_remaining' => 'required|integer|min:0|max:999',
            'expires_at'        => 'required|date',
            'status'            => 'required|in:active,expired,exhausted',
            'adjustment_note'   => 'required|string|max:1000',
        ]);

        $creditsBefore = $package->credits_remaining;
        $note          = $data['adjustment_note'];
        unset($data['adjustment_note']);

        $package->update($data);

        $package->logs()->create([
            'event_type'     => 'adjustment',
            'credits_before' => $creditsBefore,
            'credits_after'  => $package->credits_remaining,
            'note'           => $note,
            'performed_by'   => auth()->user()->name ?? auth()->user()->email,
        ]);

        return redirect()->route('read-credits.index')
            ->with('success', "Credit package for {$package->customer_email} updated.");
    }
}
