<?php

// v1.0 — 2026-06-06 | Email campaign CRUD, preview, test/live send, drag-and-drop reorder

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\EmailCampaign;
use App\Models\EmailTemplate;
use App\Services\MailerLiteService;
use App\Services\WooCommerceService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class EmailCampaignController extends Controller
{
    public function __construct(
        private MailerLiteService  $mailerlite,
        private WooCommerceService $woocommerce,
    ) {}

    // -------------------------------------------------------------------------
    // Index — three tabs: Queue / Drafts / Sent
    // -------------------------------------------------------------------------

    public function index(): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $queued = EmailCampaign::queued()->get();
        $drafts = EmailCampaign::drafts()->get();
        $sent   = EmailCampaign::sent()->get();

        return view('marketing.email-campaigns.index', compact('queued', 'drafts', 'sent'));
    }

    // -------------------------------------------------------------------------
    // Create / Store
    // -------------------------------------------------------------------------

    public function create(): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $campaign         = new EmailCampaign();
        $mailerliteGroups = $this->fetchGroups();
        $wooProducts      = $this->fetchProducts();
        $emailTemplates   = EmailTemplate::orderByDesc('updated_at')->get(['id', 'name'])->toArray();
        $initialHtml      = '';

        return view('marketing.email-campaigns.form', compact('campaign', 'mailerliteGroups', 'wooProducts', 'emailTemplates', 'initialHtml'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $this->validated($request);

        $campaign = EmailCampaign::create(array_merge($data, [
            'send_order' => EmailCampaign::max('send_order') + 1,
        ]));

        return redirect()->route('marketing.email-campaigns.edit', $campaign)
            ->with('success', 'Campaign saved.');
    }

    // -------------------------------------------------------------------------
    // Edit / Update
    // -------------------------------------------------------------------------

    public function edit(EmailCampaign $emailCampaign): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $mailerliteGroups = $this->fetchGroups();
        $wooProducts      = $this->fetchProducts();
        $emailTemplates   = EmailTemplate::orderByDesc('updated_at')->get(['id', 'name'])->toArray();
        $initialHtml      = $this->renderHtml($emailCampaign, preview: true);

        return view('marketing.email-campaigns.form', [
            'campaign'         => $emailCampaign,
            'mailerliteGroups' => $mailerliteGroups,
            'wooProducts'      => $wooProducts,
            'emailTemplates'   => $emailTemplates,
            'initialHtml'      => $initialHtml,
        ]);
    }

    public function update(Request $request, EmailCampaign $emailCampaign): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $emailCampaign->update($this->validated($request));

        return back()->with('success', 'Campaign updated.');
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function destroy(EmailCampaign $emailCampaign): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        if ($emailCampaign->image_path) {
            Storage::disk('public')->delete($emailCampaign->image_path);
        }

        $emailCampaign->delete();

        return redirect()->route('marketing.email-campaigns.index')
            ->with('success', 'Campaign deleted.');
    }

    // -------------------------------------------------------------------------
    // Duplicate as draft
    // -------------------------------------------------------------------------

    public function duplicate(EmailCampaign $emailCampaign): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $new = $emailCampaign->replicate();
        $new->status                 = 'draft';
        $new->campaign_name          = $emailCampaign->campaign_name . ' (copy)';
        $new->scheduled_at           = null;
        $new->mailerlite_campaign_id = null;
        $new->woo_coupon_id          = null;
        $new->test_sent_at           = null;
        $new->live_sent_at           = null;
        $new->send_order             = EmailCampaign::max('send_order') + 1;
        $new->save();

        return redirect()->route('marketing.email-campaigns.edit', $new)
            ->with('success', 'Duplicated as draft.');
    }

    // -------------------------------------------------------------------------
    // Status toggle (queue / pause / draft)
    // -------------------------------------------------------------------------

    public function updateStatus(Request $request, EmailCampaign $emailCampaign): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $status = $request->input('status');
        abort_unless(in_array($status, ['draft', 'queued', 'paused'], strict: true), 422);

        $emailCampaign->update(['status' => $status]);

        return response()->json(['status' => $emailCampaign->status]);
    }

    // -------------------------------------------------------------------------
    // Drag-and-drop reorder (queue only)
    // -------------------------------------------------------------------------

    public function reorder(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $ids = $request->input('order', []);
        foreach ($ids as $position => $id) {
            EmailCampaign::where('id', (int) $id)->update(['send_order' => $position]);
        }

        return response()->json(['ok' => true]);
    }

    // -------------------------------------------------------------------------
    // Image upload
    // -------------------------------------------------------------------------

    public function uploadImage(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,webp,gif|max:4096',
        ]);

        $path = $request->file('image')->store('campaign-images', 'public');
        $url  = Storage::disk('public')->url($path);

        return response()->json(['path' => $path, 'url' => $url]);
    }

    // -------------------------------------------------------------------------
    // Live preview — returns rendered HTML string
    // -------------------------------------------------------------------------

    public function preview(Request $request): Response
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        // When the HTML editor has content, return it directly so the preview reflects the custom HTML
        if ($request->filled('custom_html')) {
            return response($request->input('custom_html'))->header('Content-Type', 'text/html');
        }

        $campaign = new EmailCampaign($this->previewData($request));
        $html     = $this->renderHtml($campaign, preview: true);

        return response($html)->header('Content-Type', 'text/html');
    }

    // -------------------------------------------------------------------------
    // Send test email (creates/replaces ML draft, sends to admin email)
    // -------------------------------------------------------------------------

    public function sendTest(Request $request, EmailCampaign $emailCampaign): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        // MailerLite's new API has no documented test-send endpoint, so we render
        // the HTML and deliver it directly via the portal's configured mailer.
        try {
            $html       = $this->renderHtml($emailCampaign, preview: false);
            $subject    = '[TEST] ' . ($emailCampaign->subject_line ?: $emailCampaign->campaign_name);
            $adminEmail = auth()->user()->email;

            \Mail::html($html, function ($message) use ($adminEmail, $subject) {
                $message->to($adminEmail)->subject($subject);
            });

            $emailCampaign->update(['test_sent_at' => now()]);

            return back()->with('success', "Test email sent to {$adminEmail}.");
        } catch (\Exception $e) {
            return back()->with('error', 'Test send failed: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Send live — create WC coupon + ML campaign + schedule or send now
    // -------------------------------------------------------------------------

    public function sendLive(Request $request, EmailCampaign $emailCampaign): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        \Log::error('sendLive entered', [
            'campaign_id'            => $emailCampaign->id,
            'request_had_campaign_name' => $request->filled('campaign_name'),
            'request_coupon_code'    => $request->input('coupon_code'),
            'db_coupon_code'         => $emailCampaign->coupon_code,
            'db_woo_coupon_id'       => $emailCampaign->woo_coupon_id,
        ]);

        if (!$this->mailerlite->isConfigured()) {
            return back()->with('error', 'MailerLite API key is not configured.');
        }

        // Persist fields submitted by the schedule form so the coupon creation below
        // always uses the latest values even if the user forgot to save first.
        $patch = array_filter([
            'mailerlite_group_id'  => $request->input('mailerlite_group_id'),
            'coupon_code'          => $request->input('coupon_code'),
            'coupon_amount'        => $request->input('coupon_amount') !== null ? (float) $request->input('coupon_amount') : null,
            'coupon_type'          => in_array($request->input('coupon_type'), ['percent', 'fixed_cart']) ? $request->input('coupon_type') : null,
            'coupon_duration_days' => $request->input('coupon_duration_days') ? (int) $request->input('coupon_duration_days') : null,
        ], fn($v) => $v !== null && $v !== '');
        if ($patch) {
            $emailCampaign->update($patch);
            $emailCampaign->refresh();
        }

        if (!$emailCampaign->mailerlite_group_id) {
            return back()->with('error', 'Please select a MailerLite subscriber group before sending.');
        }

        if (!$emailCampaign->scheduled_at || !$emailCampaign->scheduled_at->isFuture()) {
            return back()->with('error', 'A future scheduled date is required before scheduling in MailerLite. Set one and save first.');
        }

        if (empty(trim($emailCampaign->subject_line))) {
            return back()->with('error', 'Subject line is required before scheduling. MailerLite will use a generated name instead of a real subject if this is blank.');
        }

        // When custom HTML is active the email uses it verbatim — field edits aren't reflected.
        // If a coupon code is set on the campaign but doesn't appear in the custom HTML, the
        // subscriber won't see a code and no WooCommerce coupon gets created — block early.
        if (!empty($emailCampaign->custom_html) && $emailCampaign->coupon_code) {
            if (!str_contains($emailCampaign->custom_html, $emailCampaign->coupon_code)) {
                return back()->with('error',
                    'Coupon code "' . $emailCampaign->coupon_code . '" is saved on this campaign but does not appear in the custom HTML — ' .
                    'subscribers won\'t see it. Go to Custom HTML → regenerate an editable copy (or paste the code in manually), then save and try again.'
                );
            }
        }

        try {
            // Create WooCommerce coupon if not already done
            if (!$emailCampaign->woo_coupon_id && $emailCampaign->coupon_code) {
                $expiryDate = null;
                if ($emailCampaign->coupon_duration_days) {
                    $base       = $emailCampaign->scheduled_at;
                    $expiryDate = $base->copy()->addDays((int) $emailCampaign->coupon_duration_days)->format('Y-m-d');
                }

                $coupon = $this->woocommerce->createCoupon(
                    code:        $emailCampaign->coupon_code,
                    type:        $emailCampaign->coupon_type ?? 'percent',
                    amount:      (float) ($emailCampaign->coupon_amount ?? 0),
                    productIds:  $emailCampaign->coupon_product_ids ?? [],
                    expiryDate:  $expiryDate,
                    description: $emailCampaign->campaign_name,
                );

                $emailCampaign->update(['woo_coupon_id' => $coupon['id']]);
            }

            // Delete previous ML draft if it exists
            if ($emailCampaign->mailerlite_campaign_id) {
                $this->mailerlite->deleteCampaign($emailCampaign->mailerlite_campaign_id);
            }

            // Create fresh ML campaign
            $html       = $this->renderHtml($emailCampaign, preview: false);
            $mlCampaign = $this->mailerlite->createCampaign(
                name:      $emailCampaign->campaign_name,
                subject:   $emailCampaign->subject_line,
                fromName:  $emailCampaign->from_name ?: 'Screenplay Readers',
                fromEmail: $emailCampaign->from_email ?: 'support@screenplayreaders.com',
                replyTo:   $emailCampaign->reply_to ?: 'support@screenplayreaders.com',
                html:      $html,
                groupIds:  [$emailCampaign->mailerlite_group_id],
            );

            $mlId = $mlCampaign['id'];
            $emailCampaign->update(['mailerlite_campaign_id' => $mlId]);

            $this->mailerlite->scheduleCampaign($mlId, $emailCampaign->scheduled_at->utc()->format('Y-m-d H:i:s'));
            $emailCampaign->update(['status' => 'queued']);

            return back()->with('success', 'Campaign scheduled in MailerLite for ' . $emailCampaign->scheduled_at->format('M j, Y g:i A') . '.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'campaign_name'       => 'required|string|max:255',
            'status'              => 'required|in:draft,queued,paused',
            'scheduled_at'        => 'nullable|date',
            'subject_line'        => 'nullable|string|max:255',
            'from_name'           => 'nullable|string|max:100',
            'from_email'          => 'nullable|email|max:255',
            'reply_to'            => 'nullable|email|max:255',
            'preheader'           => 'nullable|string|max:500',
            'headline_top'        => 'nullable|string|max:255',
            'paragraph_top1'      => 'nullable|string',
            'paragraph_top2'      => 'nullable|string',
            'url1'                => 'nullable|string|max:500',
            'headline_bottom'     => 'nullable|string|max:255',
            'paragraph_bottom'    => 'nullable|string',
            'image_path'          => 'nullable|string|max:500',
            'image_url'           => 'nullable|string|max:500',
            'coupon_code'         => 'nullable|string|max:100',
            'coupon_amount'       => 'nullable|numeric|min:0',
            'coupon_duration_days'=> 'nullable|integer|min:1',
            'coupon_type'         => 'nullable|in:percent,fixed_cart',
            'coupon_product_ids'  => 'nullable|string',  // comma-separated IDs → parsed below
            'mailerlite_group_id' => 'nullable|string|max:100',
            'custom_html'         => 'nullable|string',
        ]);

        // Coerce nullable string fields to '' — migration defines them NOT NULL with default ''
        // and MySQL strict mode rejects explicit NULL on NOT NULL columns.
        foreach (['subject_line', 'preheader', 'headline_top', 'url1', 'headline_bottom'] as $field) {
            $data[$field] = $data[$field] ?? '';
        }

        // Parse comma-separated product IDs into an array
        if (!empty($data['coupon_product_ids'])) {
            $data['coupon_product_ids'] = array_values(array_filter(
                array_map('trim', explode(',', $data['coupon_product_ids']))
            ));
        } else {
            $data['coupon_product_ids'] = null;
        }

        return $data;
    }

    private function previewData(Request $request): array
    {
        return [
            'subject_line'        => $request->input('subject_line', ''),
            'preheader'           => $request->input('preheader', ''),
            'headline_top'        => $request->input('headline_top', ''),
            'paragraph_top1'      => $request->input('paragraph_top1', ''),
            'paragraph_top2'      => $request->input('paragraph_top2', ''),
            'url1'                => $request->input('url1', '#'),
            'headline_bottom'     => $request->input('headline_bottom', ''),
            'paragraph_bottom'    => $request->input('paragraph_bottom', ''),
            'image_url'           => $request->input('image_url', ''),
            'coupon_code'         => $request->input('coupon_code', ''),
            'coupon_duration_days'=> $request->input('coupon_duration_days'),
            'scheduled_at'        => $request->input('scheduled_at'),
        ];
    }

    private function fetchGroups(): array
    {
        if (!$this->mailerlite->isConfigured()) {
            return [];
        }

        try {
            return $this->mailerlite->getGroups();
        } catch (\RuntimeException) {
            return [];
        }
    }

    private function fetchProducts(): array
    {
        try {
            return $this->woocommerce->getProducts();
        } catch (\RuntimeException) {
            return [];
        }
    }

    /**
     * Render the email HTML template with campaign data.
     * $preview = true replaces MailerLite merge tags with readable placeholders.
     * If the campaign has custom_html saved, that takes precedence over the template.
     */
    private function renderHtml(EmailCampaign $campaign, bool $preview = false): string
    {
        if (!empty($campaign->custom_html)) {
            return $campaign->custom_html;
        }

        $couponCode = $campaign->coupon_code ?? '';

        $expiryDate = '';
        if ($campaign->coupon_duration_days) {
            $base       = $campaign->scheduled_at ?? now();
            $expiryDate = $base->copy()->addDays((int) $campaign->coupon_duration_days)->format('F j, Y');
        }

        $html = view('marketing.email-campaigns.partials.email-html', [
            'subjectLine'     => $campaign->subject_line ?? '',
            'preheader'       => $campaign->preheader ?? '',
            'headlineTop'     => $campaign->headline_top ?? '',
            'paragraphTop1'   => $campaign->paragraph_top1 ?? '',
            'paragraphTop2'   => $campaign->paragraph_top2 ?? '',
            'couponCode'      => $couponCode,
            'couponExpiry'    => $expiryDate,
            'couponFinePrint' => $expiryDate ? "Coupon expires {$expiryDate}" : '',
            'url1'            => $campaign->url1 ?: '#',
            'headlineBottom'  => $campaign->headline_bottom ?? '',
            'paragraphBottom' => $campaign->paragraph_bottom ?? '',
            'imageUrl'        => $campaign->image_url ?? '',
            'preview'         => $preview,
        ])->render();

        // Bold every occurrence of the coupon code in the HTML body
        if ($couponCode) {
            $escaped = preg_quote($couponCode, '/');
            $html    = preg_replace(
                '/(?<!<strong>)' . $escaped . '(?!<\/strong>)/',
                '<strong>' . $couponCode . '</strong>',
                $html
            );
        }

        return $html;
    }
}
