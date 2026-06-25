<?php

// v1.0 — 2026-06-08 | Partner backlink monitor — CRUD, check-now, uptime stats

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\PartnerLinkCheck;
use App\Models\PartnerSite;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class PartnerSiteController extends Controller
{
    const TARGET_DOMAIN = 'screenplayreaders.com';

    public static array $PERIODS = [
        'last_7'     => 'Last 7 Days',
        'last_30'    => 'Last 30 Days',
        'last_90'    => 'Last 90 Days',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'this_year'  => 'This Year',
        'last_year'  => 'Last Year',
        'all_time'   => 'All Time',
    ];

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function index(Request $request): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $period = $request->input('period', 'last_30');
        if (!array_key_exists($period, self::$PERIODS)) {
            $period = 'last_30';
        }

        [$start, $end] = $this->dateRange($period);

        $sites = PartnerSite::with(['latestCheck'])->orderBy('name')->get();

        // Attach uptime + check counts for the selected period
        $stats = $sites->map(function (PartnerSite $site) use ($start, $end) {
            $query = $site->checks()
                ->when($start, fn($q) => $q->where('checked_at', '>=', $start))
                ->when($end,   fn($q) => $q->where('checked_at', '<=', $end));

            $total   = $query->count();
            $upCount = (clone $query)->where('is_up', true)->count();

            return [
                'total'   => $total,
                'up'      => $upCount,
                'uptime'  => $total > 0 ? round(($upCount / $total) * 100, 1) : null,
            ];
        })->keyBy(fn($v, $k) => $sites[$k]->id);

        $partnerFormSettings = \App\Models\Setting::getPartnerFormSettings();

        return view('marketing.partner-sites.index', compact('sites', 'stats', 'period', 'start', 'end', 'partnerFormSettings'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $this->validated($request);
        $site = PartnerSite::create($data);

        if (!empty($site->coupon_code)) {
            self::syncWcCouponSettings($site);
        }

        return redirect()->route('marketing.partner-sites.index')
            ->with('success', 'Partner site added.');
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function update(Request $request, PartnerSite $partnerSite): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $this->validated($request);

        // If URL or interval changed, reschedule immediately
        if ($data['url'] !== $partnerSite->url || $data['check_interval_minutes'] !== $partnerSite->check_interval_minutes) {
            $data['next_check_at'] = null;
        }

        $partnerSite->update($data);

        if (!empty($partnerSite->coupon_code)) {
            self::syncWcCouponSettings($partnerSite);
        }

        return redirect()->route('marketing.partner-sites.index')
            ->with('success', 'Partner site updated.');
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function destroy(PartnerSite $partnerSite): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $partnerSite->delete(); // cascades to checks

        return redirect()->route('marketing.partner-sites.index')
            ->with('success', 'Partner site deleted.');
    }

    // -------------------------------------------------------------------------
    // Check Now (AJAX)
    // -------------------------------------------------------------------------

    public function checkNow(PartnerSite $partnerSite): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $result = $this->runCheck($partnerSite);

        $latest = $partnerSite->latestCheck()->first();

        return response()->json([
            'check'  => $result,
            'latest' => $latest ? [
                'checked_at'       => $latest->checked_at->diffForHumans(),
                'is_up'            => $latest->is_up,
                'http_status'      => $latest->http_status,
                'response_time_ms' => $latest->response_time_ms,
                'links_found'      => $latest->links_found,
                'error_message'    => $latest->error_message,
            ] : null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Toggle active
    // -------------------------------------------------------------------------

    public function toggleActive(PartnerSite $partnerSite): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $partnerSite->update(['active' => !$partnerSite->active]);

        return response()->json(['active' => $partnerSite->active]);
    }

    // -------------------------------------------------------------------------
    // Check history (last N checks for one site, returned as JSON)
    // -------------------------------------------------------------------------

    public function history(PartnerSite $partnerSite): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $checks = $partnerSite->checks()
            ->orderByDesc('checked_at')
            ->limit(50)
            ->get(['checked_at', 'is_up', 'http_status', 'response_time_ms', 'links_found', 'error_message'])
            ->map(fn($c) => [
                'checked_at'       => $c->checked_at->format('M j, Y g:i A'),
                'is_up'            => $c->is_up,
                'http_status'      => $c->http_status,
                'response_time_ms' => $c->response_time_ms,
                'links_found'      => $c->links_found ?? [],
                'error_message'    => $c->error_message,
            ]);

        return response()->json($checks);
    }

    // -------------------------------------------------------------------------
    // Shared check runner (used by checkNow and the artisan command)
    // -------------------------------------------------------------------------

    public static function runCheck(PartnerSite $site): array
    {
        $start = microtime(true);
        $links = [];
        $httpStatus = null;
        $errorMessage = null;
        $isUp = false;

        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SRLinkMonitor/1.0)'])
                ->get($site->url);

            $httpStatus = $response->status();
            $elapsed    = (int) round((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                $links = self::extractLinks($response->body());
                $isUp  = count($links) > 0;
            } else {
                $errorMessage = "HTTP {$httpStatus}";
            }
        } catch (\Throwable $e) {
            $elapsed      = (int) round((microtime(true) - $start) * 1000);
            $errorMessage = $e->getMessage();
        }

        $check = PartnerLinkCheck::create([
            'partner_site_id'  => $site->id,
            'checked_at'       => now(),
            'is_up'            => $isUp,
            'http_status'      => $httpStatus,
            'response_time_ms' => $elapsed ?? null,
            'links_found'      => $links ?: null,
            'error_message'    => $errorMessage,
        ]);

        $site->update(['next_check_at' => now()->addMinutes($site->check_interval_minutes)]);

        self::syncCouponStatus($site, $isUp);

        return [
            'is_up'            => $isUp,
            'http_status'      => $httpStatus,
            'response_time_ms' => $check->response_time_ms,
            'links_found'      => $links,
            'error_message'    => $errorMessage,
        ];
    }

    // -------------------------------------------------------------------------
    // WooCommerce coupon sync
    // -------------------------------------------------------------------------

    public const COUPON_PROBATION_DAYS = 7;

    /**
     * Enable or disable the partner's WooCommerce coupon after each check.
     *
     * If coupon_uptime_threshold is set, the decision uses rolling uptime over the
     * last 20 checks: enabled when uptime% >= threshold, disabled when below.
     * If null, the decision is per-check: enabled only when this check found a backlink.
     *
     * New coupons must meet the threshold continuously for 7 days before activating.
     * coupon_eligible_at tracks when the threshold was first met; it resets if the
     * threshold drops below. The coupon only enables once the probation period passes.
     */
    private static function syncCouponStatus(PartnerSite $site, bool $isUp): void
    {
        $code = trim((string) ($site->coupon_code ?? ''));
        if ($code === '') return;

        $threshold = $site->coupon_uptime_threshold; // float|null

        if ($threshold !== null) {
            $recent  = $site->checks()->orderByDesc('checked_at')->limit(20)->pluck('is_up');
            $total   = $recent->count();
            $uptime  = $total > 0 ? ($recent->filter()->count() / $total) * 100 : 0.0;
            $meetsThreshold = $uptime >= $threshold;
        } else {
            $meetsThreshold = $isUp;
        }

        if ($meetsThreshold) {
            if (! $site->coupon_eligible_at) {
                $site->update(['coupon_eligible_at' => now()]);
            }
            $enable = $site->coupon_eligible_at->addDays(self::COUPON_PROBATION_DAYS)->isPast();
        } else {
            if ($site->coupon_eligible_at) {
                $site->update(['coupon_eligible_at' => null]);
            }
            $enable = false;
        }

        [$storeUrl, $ck, $cs] = self::wcConfig();
        if ($storeUrl === '') return;

        try {
            $couponId = self::wcFindCouponId($code, $storeUrl, $ck, $cs);
            if (!$couponId) return;

            $status = $enable ? 'publish' : 'draft';
            Http::withBasicAuth($ck, $cs)
                ->timeout(10)
                ->put("{$storeUrl}/wp-json/wc/v3/coupons/{$couponId}", ['status' => $status]);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                "Partner coupon sync failed for site {$site->id} (coupon: {$code}): " . $e->getMessage()
            );
        }
    }

    /**
     * Create or update the partner's WooCommerce coupon with the portal-managed settings:
     * discount type, amount, and individual_use: false (always combinable).
     *
     * - If the coupon doesn't exist: creates it as 'publish'.
     * - If it already exists: updates discount fields only — does NOT touch 'status'
     *   so monitoring-driven enable/disable (syncCouponStatus) is preserved.
     */
    public static function syncWcCouponSettings(PartnerSite $site): void
    {
        $code = trim((string) ($site->coupon_code ?? ''));
        if ($code === '') return;

        [$storeUrl, $ck, $cs] = self::wcConfig();
        if ($storeUrl === '') return;

        $discountType = $site->coupon_discount_type ?: 'percent';
        $amount       = $site->coupon_amount !== null ? (string) $site->coupon_amount : '0';

        $payload = [
            'discount_type'  => $discountType,
            'amount'         => $amount,
            'individual_use' => false,
        ];

        try {
            $couponId = self::wcFindCouponId($code, $storeUrl, $ck, $cs);

            if (!$couponId) {
                // Create new — publish immediately; monitoring will manage status from first check.
                Http::withBasicAuth($ck, $cs)
                    ->timeout(10)
                    ->post("{$storeUrl}/wp-json/wc/v3/coupons", array_merge($payload, [
                        'code'        => $code,
                        'status'      => 'publish',
                        'description' => 'Partner referral coupon — managed by Partner Link Monitor.',
                    ]));
            } else {
                // Update discount settings only; leave status untouched.
                Http::withBasicAuth($ck, $cs)
                    ->timeout(10)
                    ->put("{$storeUrl}/wp-json/wc/v3/coupons/{$couponId}", $payload);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                "Partner coupon sync failed (coupon: {$code}): " . $e->getMessage()
            );
        }
    }

    /** Returns the WC numeric coupon ID for the given code, or null if not found. */
    private static function wcFindCouponId(string $code, string $storeUrl, string $ck, string $cs): ?int
    {
        $response = Http::withBasicAuth($ck, $cs)
            ->timeout(10)
            ->get("{$storeUrl}/wp-json/wc/v3/coupons", ['code' => $code, 'per_page' => 1]);

        if (!$response->successful()) return null;

        $id = $response->json()[0]['id'] ?? null;
        return $id ? (int) $id : null;
    }

    /** Returns [storeUrl, consumerKey, consumerSecret], or ['', '', ''] if unconfigured. */
    private static function wcConfig(): array
    {
        $url = rtrim((string) config('services.woocommerce.store_url', ''), '/');
        $ck  = (string) config('services.woocommerce.consumer_key', '');
        $cs  = (string) config('services.woocommerce.consumer_secret', '');
        if ($url === '' || $ck === '' || $cs === '') return ['', '', ''];
        return [$url, $ck, $cs];
    }

    // -------------------------------------------------------------------------
    // HTML link extraction
    // -------------------------------------------------------------------------

    private static function extractLinks(string $html): array
    {
        $links = [];

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $href = (string) $anchor->getAttribute('href');
            if (!str_contains($href, self::TARGET_DOMAIN)) {
                continue;
            }

            $rel         = strtolower((string) $anchor->getAttribute('rel'));
            $relParts    = preg_split('/\s+/', trim($rel));
            $isNofollow  = in_array('nofollow', $relParts, strict: true);
            $isSponsored = in_array('sponsored', $relParts, strict: true);
            $isUgc       = in_array('ugc', $relParts, strict: true);
            $isDofollow  = !$isNofollow && !$isSponsored;

            $links[] = [
                'href'         => $href,
                'anchor_text'  => trim(strip_tags($anchor->textContent)),
                'rel'          => $rel ?: null,
                'is_dofollow'  => $isDofollow,
                'is_nofollow'  => $isNofollow,
                'is_sponsored' => $isSponsored,
                'is_ugc'       => $isUgc,
            ];
        }

        return $links;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'                    => 'required|string|max:255',
            'url'                     => 'required|url|max:500',
            'check_interval_minutes'  => 'required|integer|min:5|max:43200',
            'active'                  => 'nullable|boolean',
            'notes'                   => 'nullable|string|max:1000',
            'coupon_code'             => 'nullable|string|max:255',
            'coupon_discount_type'    => 'nullable|in:percent,fixed_cart',
            'coupon_amount'           => 'nullable|numeric|min:0',
            'coupon_uptime_threshold' => 'nullable|numeric|min:0|max:100',
        ]) + ['active' => $request->has('active') ? (bool) $request->input('active') : true];
    }

    // -------------------------------------------------------------------------
    // Partner form settings
    // -------------------------------------------------------------------------

    public function updateFormSettings(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $keys = array_keys(\App\Models\Setting::PARTNER_FORM_DEFAULTS);

        $intKeys = ['partner_form_discount_percent', 'partner_form_uptime_threshold', 'partner_form_check_interval_minutes'];

        $data = $request->validate(
            collect($keys)->mapWithKeys(fn($k) => [
                $k => match (true) {
                    $k === 'partner_form_discount_percent'       => 'required|integer|min:1|max:100',
                    $k === 'partner_form_uptime_threshold'       => 'required|integer|min:0|max:100',
                    $k === 'partner_form_check_interval_minutes' => 'required|integer|min:5|max:43200',
                    default                                      => 'required|string|max:1000',
                },
            ])->all()
        );

        foreach ($keys as $key) {
            \App\Models\Setting::setValue($key, in_array($key, $intKeys)
                ? (int) $data[$key]
                : trim($data[$key])
            );
        }

        return redirect()->route('marketing.partner-sites.index')
            ->with('success', 'Partner form settings saved.');
    }

    private function dateRange(string $period): array
    {
        $tz  = config('app.timezone', 'America/Los_Angeles');
        $now = Carbon::now($tz);

        return match ($period) {
            'last_7'     => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_30'    => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'last_90'    => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'this_year'  => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year'  => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'all_time'   => [null, null],
            default      => [null, null],
        };
    }
}
