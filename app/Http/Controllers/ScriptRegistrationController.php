<?php

// v1.1 — 2026-06-22 | Add test form for end-to-end pipeline testing
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

    // ── Test Form ──

    public function testForm()
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        return view('script-registrations.test', [
            'result'     => session('test_result'),
            'workTypes'  => ['Feature Screenplay', 'TV Pilot', 'Short', 'Treatment', 'Pitch Deck', 'Other'],
            'variations' => ScriptRegistration::VARIATION_LABELS,
        ]);
    }

    public function testRun(Request $request)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $data = $request->validate([
            'test_email'    => 'required|email|max:255',
            'variation_id'  => 'required|integer|in:' . implode(',', array_keys(ScriptRegistration::VARIATION_LABELS)),
            'title'         => 'required|string|max:255',
            'page_count'    => 'required|integer|min:1|max:9999',
            'type_of_work'  => 'required|string|max:120',
            'author_first'  => 'required|string|max:120',
            'author_last'   => 'required|string|max:120',
        ]);

        $variationId = (int) $data['variation_id'];

        $registration = ScriptRegistration::create([
            'woo_order_id'    => 'TEST-' . now()->format('YmdHis') . '-' . auth()->id(),
            'woo_order_number' => null,
            'customer_name'   => trim($data['author_first'] . ' ' . $data['author_last']),
            'customer_email'  => $data['test_email'],
            'variation_id'    => $variationId,
            'variation_label' => ScriptRegistration::VARIATION_LABELS[$variationId] ?? 'Unknown',
            'registration_id' => ScriptRegistration::generateRegistrationId(),
            'script_title'    => $data['title'],
            'page_count'      => (int) $data['page_count'],
            'type_of_work'    => $data['type_of_work'],
            'author_first'    => $data['author_first'],
            'author_last'     => $data['author_last'],
            'additional_authors' => null,
            'street_address'  => '123 Test Street',
            'city'            => 'Los Angeles',
            'state_or_province' => 'CA',
            'postal_or_zip'   => '90001',
            'country'         => 'United States',
            'phone'           => '555-0100',
            'unique_id'       => null,
            'email'           => $data['test_email'],
            'uploaded_file_url' => null,
            'uploaded_file_name' => null,
            'authcode'        => bin2hex(random_bytes(16)),
            'registered_at'   => now(),
            'expires_at'      => match ($variationId) {
                ScriptRegistration::VAR_FREE_90 => now()->addDays(90),
                ScriptRegistration::VAR_5YR     => now()->addYears(5),
                ScriptRegistration::VAR_10YR    => now()->addYears(10),
                ScriptRegistration::VAR_LIFETIME => null,
                default => now()->addDays(90),
            },
            'unlimited_token' => $variationId === ScriptRegistration::VAR_LIFETIME
                ? bin2hex(random_bytes(32))
                : null,
            'status'          => ScriptRegistration::STATUS_PENDING,
        ]);

        GenerateRegistrationCertificate::dispatch($registration->id);

        return redirect()->route('script-registrations.test')
            ->with('test_result', [
                'id'              => $registration->id,
                'registration_id' => $registration->registration_id,
                'email'           => $data['test_email'],
                'variation'       => $registration->variation_label,
                'unlimited_url'   => $registration->publicRegistrationUrl(),
            ])
            ->with('success', "Test registration {$registration->registration_id} created and queued for delivery to {$data['test_email']}.");
    }
}
