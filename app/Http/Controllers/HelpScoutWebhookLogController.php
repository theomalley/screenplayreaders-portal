<?php

// v1.0 — 2026-06-12 | Admin viewer for incoming HelpScout webhook deliveries (helpscout_webhook_logs)

namespace App\Http\Controllers;

use App\Models\HelpScoutWebhookLog;

class HelpScoutWebhookLogController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $logs = HelpScoutWebhookLog::latest()->paginate(50);

        return view('admin.helpscout-webhook-logs', compact('logs'));
    }
}
