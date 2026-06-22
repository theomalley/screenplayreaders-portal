<?php

// v1.0 — 2026-06-22 | Initial: admin panel for script registrations — list, detail,
//                      certificate download/regenerate, unlimited token management.

namespace App\Http\Controllers;

use App\Jobs\GenerateRegistrationCertificate;
use App\Models\ScriptRegistration;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;

class ScriptRegistrationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $q         = trim((string) $request->input('q', ''));
        $status    = $request->input('status', 'all');
        $variation = $request->input('variation', 'all');

        $query = ScriptRegistration::query()
            ->withCount('children')
            ->orderByDesc('created_at');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('customer_email', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('registration_id', 'like', "%{$q}%")
                    ->orWhere('woo_order_number', 'like', "%{$q}%")
                    ->orWhere('script_title', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($variation !== 'all') {
            $query->where('variation_id', (int) $variation);
        }

        $registrations = $query->paginate(25)->withQueryString();

        return view('script-registrations.index', [
            'registrations' => $registrations,
            'q'             => $q,
            'status'        => $status,
            'variation'     => $variation,
        ]);
    }

    public function show(ScriptRegistration $registration)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $registration->load('children', 'parent');

        return view('script-registrations.show', [
            'registration' => $registration,
        ]);
    }

    public function regenerateCertificate(ScriptRegistration $registration)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $registration->update([
            'status'        => ScriptRegistration::STATUS_PENDING,
            'error_message' => null,
        ]);

        GenerateRegistrationCertificate::dispatch($registration->id);

        return back()->with('success', 'Certificate regeneration queued for ' . $registration->registration_id . '.');
    }

    public function downloadCertificate(ScriptRegistration $registration, GoogleDriveService $drive)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        if (! $registration->drive_certificate_pdf_id) {
            return back()->withErrors(['download' => 'No certificate PDF available. Try regenerating first.']);
        }

        $bytes = $drive->downloadContents($registration->drive_certificate_pdf_id);
        $safeTitle = preg_replace('/[^\w\s\-.]/', '', $registration->script_title);
        $filename = "SR Registration Certificate - {$safeTitle} - {$registration->registration_id}.pdf";

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => strlen($bytes),
        ]);
    }

    public function regenerateToken(ScriptRegistration $registration)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        if (! $registration->isUnlimited()) {
            return back()->withErrors(['token' => 'Only unlimited registrations have tokens.']);
        }

        $registration->update([
            'unlimited_token' => bin2hex(random_bytes(32)),
        ]);

        return back()->with('success', 'Unlimited token regenerated for ' . $registration->registration_id . '.');
    }
}
