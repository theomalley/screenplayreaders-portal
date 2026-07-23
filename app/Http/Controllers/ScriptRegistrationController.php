<?php

// v1.2 — 2026-07-23 | Authorization moved to ScriptRegistrationPolicy (app/Policies),
//                     replacing inline abort_unless(...) calls. Covered by
//                     tests/Feature/ScriptRegistrationControllerTest.php.
// v1.1 — 2026-06-22 | Add test form for end-to-end pipeline testing
// v1.0 — 2026-06-22 | Initial: admin panel for script registrations — list, detail,
//                      certificate download/regenerate, unlimited token management.

namespace App\Http\Controllers;

use App\Jobs\GenerateRegistrationCertificate;
use App\Models\ScriptRegistration;
use App\Services\GoogleDocsService;
use App\Services\SpacesStorageService;
use Illuminate\Http\Request;

class ScriptRegistrationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', ScriptRegistration::class);

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

        // Built from variations that actually occur, not the legacy hardcoded 4-ID map —
        // so a variation added via the theme's SR Registration Form Config tool shows up
        // here as soon as an order using it has come through.
        $variationOptions = ScriptRegistration::query()
            ->select('variation_id', 'variation_label')
            ->distinct()
            ->orderBy('variation_label')
            ->get();

        return view('script-registrations.index', [
            'registrations'    => $registrations,
            'q'                => $q,
            'status'           => $status,
            'variation'        => $variation,
            'variationOptions' => $variationOptions,
        ]);
    }

    public function show(ScriptRegistration $script_registration)
    {
        $this->authorize('view', $script_registration);

        $script_registration->load('children', 'parent');

        return view('script-registrations.show', [
            'registration' => $script_registration,
        ]);
    }

    public function regenerateCertificate(ScriptRegistration $script_registration)
    {
        $this->authorize('regenerateCertificate', $script_registration);

        $script_registration->update([
            'status'        => ScriptRegistration::STATUS_PENDING,
            'error_message' => null,
        ]);

        GenerateRegistrationCertificate::dispatch($script_registration->id);

        return back()->with('success', 'Certificate regeneration queued for ' . $script_registration->registration_id . '.');
    }

    public function downloadCertificate(ScriptRegistration $script_registration, GoogleDocsService $docs, SpacesStorageService $spaces)
    {
        $this->authorize('download', $script_registration);

        if (! $script_registration->drive_certificate_pdf_id) {
            return back()->withErrors(['download' => 'No certificate PDF available. Try regenerating first.']);
        }

        $bytes = $script_registration->spaces_certificate_pdf_path
            ? $spaces->get($script_registration->spaces_certificate_pdf_path)
            : $docs->downloadDriveFileBytes($script_registration->drive_certificate_pdf_id);
        $safeTitle = preg_replace('/[^\w\s\-.]/', '', $script_registration->script_title);
        $orderPrefix = $script_registration->woo_order_number ? "{$script_registration->woo_order_number} - " : '';
        $filename = "{$orderPrefix}SR Registration Certificate - {$safeTitle} - {$script_registration->registration_id}.pdf";

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => strlen($bytes),
        ]);
    }

    public function downloadScript(ScriptRegistration $script_registration, SpacesStorageService $spaces)
    {
        $this->authorize('download', $script_registration);

        if (! $script_registration->spaces_script_file_path) {
            return back()->withErrors(['download' => 'No script file available for this registration.']);
        }

        $bytes = $spaces->get($script_registration->spaces_script_file_path);
        $ext = pathinfo($script_registration->spaces_script_file_path, PATHINFO_EXTENSION) ?: 'pdf';
        $safeTitle = preg_replace('/[^\w\s\-.]/', '', $script_registration->script_title);
        $filename = "{$safeTitle} - {$script_registration->registration_id}.{$ext}";

        return response($bytes, 200, [
            'Content-Type'        => match ($ext) {
                'pdf' => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                default => 'application/octet-stream',
            },
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => strlen($bytes),
        ]);
    }

    public function regenerateToken(ScriptRegistration $script_registration)
    {
        $this->authorize('regenerateToken', $script_registration);

        if (! $script_registration->isUnlimited()) {
            return back()->withErrors(['token' => 'Only unlimited registrations have tokens.']);
        }

        $script_registration->update([
            'unlimited_token' => ScriptRegistration::generateUnlimitedToken(),
        ]);

        return back()->with('success', 'Unlimited token regenerated for ' . $script_registration->registration_id . '.');
    }

    public function bulkDestroy(Request $request)
    {
        $this->authorize('bulkDelete', ScriptRegistration::class);

        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $count = ScriptRegistration::whereIn('id', $data['ids'])->delete();

        return back()->with('success', $count . ' registration' . ($count === 1 ? '' : 's') . ' deleted.');
    }

    public function destroy(ScriptRegistration $script_registration)
    {
        $this->authorize('delete', $script_registration);

        $regId = $script_registration->registration_id;
        $script_registration->delete();

        return redirect()->route('script-registrations.index')
            ->with('success', "Registration {$regId} deleted.");
    }

    // ── Test Form ──

    public function testForm()
    {
        $this->authorize('useTestTools', ScriptRegistration::class);

        return view('script-registrations.test', [
            'result'     => session('test_result'),
            'workTypes'  => ['Feature Screenplay', 'TV Pilot', 'Short', 'Treatment', 'Pitch Deck', 'Other'],
            'variations' => ScriptRegistration::VARIATION_LABELS,
        ]);
    }

    public function testRun(Request $request)
    {
        $this->authorize('useTestTools', ScriptRegistration::class);

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
                ? ScriptRegistration::generateUnlimitedToken()
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
