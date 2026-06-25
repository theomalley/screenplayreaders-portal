<?php

// v1.1 — 2026-06-23 | Security hardening: MIME magic-byte verification on uploads, minimum
//                      file size check, UUID-based safe filenames, strict extension matching
// v1.0 — 2026-06-22 | Initial: public form for unlimited script registration token holders.
//                      No auth required — token in URL identifies the unlimited purchase.

namespace App\Http\Controllers;

use App\Jobs\GenerateRegistrationCertificate;
use App\Models\ScriptRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ScriptRegistrationPublicController extends Controller
{
    private const ALLOWED_WORK_TYPES = [
        'Feature Screenplay',
        'TV Pilot',
        'Short',
        'Treatment',
        'Pitch Deck',
        'Other',
    ];

    private const MAX_FILE_SIZE_KB = 5120;

    private const ALLOWED_EXTENSIONS = ['pdf', 'docx', 'fdx', 'fdr', 'fadein', 'fountain'];

    public function show(string $token)
    {
        $parent = $this->findParent($token);
        $registrations = $parent->children()->orderByDesc('registered_at')->get();
        $registrations->prepend($parent);

        return view('script-registrations.public-form', [
            'parent'        => $parent,
            'token'         => $token,
            'workTypes'     => self::ALLOWED_WORK_TYPES,
            'registrations' => $registrations,
        ]);
    }

    private const DAILY_SUBMISSION_LIMIT = 10;

    public function submit(Request $request, string $token)
    {
        $parent = $this->findParent($token);

        $todayCount = ScriptRegistration::where('unlimited_token_parent_id', $parent->id)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        if ($todayCount >= self::DAILY_SUBMISSION_LIMIT) {
            return back()->withInput()->withErrors([
                'sr_title' => 'Daily registration limit reached. Please try again tomorrow.',
            ]);
        }

        $data = $request->validate([
            'sr_title'              => 'required|string|max:255',
            'sr_page_count'         => 'required|integer|min:1|max:9999',
            'sr_type_of_work'       => 'required|string|in:' . implode(',', self::ALLOWED_WORK_TYPES),
            'sr_author_first'       => 'required|string|max:120',
            'sr_author_last'        => 'required|string|max:120',
            'sr_additional_authors' => 'nullable|string|max:2000',
            'sr_street_address'     => 'required|string|max:200',
            'sr_city'               => 'required|string|max:120',
            'sr_state_or_province'  => 'required|string|max:120',
            'sr_postal_or_zip'      => 'required|string|max:40',
            'sr_country'            => 'required|string|max:120',
            'sr_phone'              => 'required|string|max:60',
            'sr_unique_id'          => 'nullable|string|max:120',
            'sr_email'              => 'required|email|max:255',
            'sr_file'               => 'required|file|max:' . self::MAX_FILE_SIZE_KB,
        ]);

        $file = $request->file('sr_file');
        $ext = strtolower($file->getClientOriginalExtension());

        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return back()->withInput()->withErrors([
                'sr_file' => 'File type not allowed. Accepted: ' . implode(', ', self::ALLOWED_EXTENSIONS),
            ]);
        }

        if ($file->getSize() < 1024) {
            return back()->withInput()->withErrors([
                'sr_file' => 'File is too small to be a valid document.',
            ]);
        }

        if (! $this->verifyMime($file->getRealPath(), $ext)) {
            return back()->withInput()->withErrors([
                'sr_file' => 'File contents do not match the expected type. Please upload a valid file.',
            ]);
        }

        $regId = ScriptRegistration::generateRegistrationId();
        $safeName = Str::uuid() . '.' . $ext;
        $storedPath = $file->storeAs('incoming-registrations/' . $regId, $safeName);

        $registration = ScriptRegistration::create([
            'woo_order_id'          => 'UNLIMITED-' . $parent->woo_order_id . '-' . uniqid(),
            'woo_order_number'      => null,
            'customer_name'         => trim($data['sr_author_first'] . ' ' . $data['sr_author_last']),
            'customer_email'        => $data['sr_email'],
            'variation_id'          => ScriptRegistration::VAR_LIFETIME,
            'variation_label'       => 'Unlimited',
            'registration_id'       => $regId,
            'script_title'          => $data['sr_title'],
            'page_count'            => (int) $data['sr_page_count'],
            'type_of_work'          => $data['sr_type_of_work'],
            'author_first'          => $data['sr_author_first'],
            'author_last'           => $data['sr_author_last'],
            'additional_authors'    => $data['sr_additional_authors'] ?: null,
            'street_address'        => $data['sr_street_address'],
            'city'                  => $data['sr_city'],
            'state_or_province'     => $data['sr_state_or_province'],
            'postal_or_zip'         => $data['sr_postal_or_zip'],
            'country'               => $data['sr_country'],
            'phone'                 => $data['sr_phone'],
            'unique_id'             => $data['sr_unique_id'] ?: null,
            'email'                 => $data['sr_email'],
            'uploaded_file_url'     => $storedPath,
            'uploaded_file_name'    => $file->getClientOriginalName(),
            'authcode'              => bin2hex(random_bytes(16)),
            'registered_at'         => now(),
            'expires_at'            => null,
            'unlimited_token_parent_id' => $parent->id,
            'status'                => ScriptRegistration::STATUS_PENDING,
        ]);

        GenerateRegistrationCertificate::dispatch($registration->id);

        return back()->with('success', 'Your script has been registered! A certificate will be emailed to ' . $data['sr_email'] . ' shortly.');
    }

    private function findParent(string $token): ScriptRegistration
    {
        $parent = ScriptRegistration::where('unlimited_token', $token)->first();

        abort_if(! $parent, 404, 'Invalid registration token.');
        abort_if(! $parent->isUnlimited(), 404, 'Invalid registration token.');

        return $parent;
    }

    private const ALLOWED_MIMES = [
        'pdf'      => ['application/pdf'],
        'docx'     => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'fdx'      => ['application/xml', 'text/xml', 'text/plain'],
        'fdr'      => ['application/zip', 'application/octet-stream'],
        'fadein'   => ['application/zip'],
        'fountain' => ['text/plain', 'application/octet-stream'],
    ];

    private function verifyMime(string $path, string $ext): bool
    {
        if (! isset(self::ALLOWED_MIMES[$ext])) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (! $finfo) {
            return false;
        }

        $detected = finfo_file($finfo, $path);
        finfo_close($finfo);

        if ($detected === false) {
            return false;
        }

        $detected = strtolower(trim(explode(';', $detected)[0]));

        return in_array($detected, self::ALLOWED_MIMES[$ext], true);
    }
}
