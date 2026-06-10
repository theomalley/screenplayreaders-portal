<?php

// v1.0 — 2026-06-10 | Issue and reset signed, expiring, single-use script download links for readers.

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\ScriptDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ScriptDownloadController extends Controller
{
    private const LINK_LIFETIME_MINUTES = 15;

    /**
     * Reader requests a fresh download link for their own assignment's script.
     */
    public function store(Request $request, Assignment $assignment)
    {
        abort_unless(
            $request->user()->isReader() && $assignment->assigned_reader_id === $request->user()->id(),
            403
        );
        abort_unless($assignment->hasCloudScript(), 404);

        $scriptDownload = ScriptDownload::create([
            'assignment_id' => $assignment->id,
            'user_id'       => $request->user()->id(),
            'token'         => Str::random(40),
            'expires_at'    => now()->addMinutes(self::LINK_LIFETIME_MINUTES),
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);

        return response()->json([
            'url' => $this->signedDownloadUrl($assignment, $scriptDownload),
        ]);
    }

    /**
     * Admin/editor regenerates an expired, unused download link.
     */
    public function reset(Request $request, ScriptDownload $scriptDownload)
    {
        $this->authorize('update', $scriptDownload->assignment);

        abort_unless(
            $scriptDownload->used_at === null && $scriptDownload->expires_at?->isPast(),
            422,
            'This link has not expired.'
        );

        $scriptDownload->update([
            'token'      => Str::random(40),
            'expires_at' => now()->addMinutes(self::LINK_LIFETIME_MINUTES),
        ]);

        return back()->with('download_link', $this->signedDownloadUrl($scriptDownload->assignment, $scriptDownload));
    }

    private function signedDownloadUrl(Assignment $assignment, ScriptDownload $scriptDownload): string
    {
        return URL::temporarySignedRoute('assignments.downloadScript', $scriptDownload->expires_at, [
            'assignment' => $assignment->id,
            'token'      => $scriptDownload->token,
        ]);
    }
}
