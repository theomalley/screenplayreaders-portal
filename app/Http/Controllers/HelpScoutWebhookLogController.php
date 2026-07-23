<?php

// v1.1 — 2026-07-23 | Authorization moved to the view-helpscout-webhook-logs Gate ability (AppServiceProvider)
// v1.0 — 2026-06-12 | Admin viewer for incoming HelpScout webhook deliveries (helpscout_webhook_logs)

namespace App\Http\Controllers;

use App\Models\HelpScoutWebhookLog;

class HelpScoutWebhookLogController extends Controller
{
    public function index()
    {
        $this->authorize('view-helpscout-webhook-logs');

        $logs = HelpScoutWebhookLog::latest()->paginate(50);

        return view('admin.helpscout-webhook-logs', compact('logs'));
    }
}
