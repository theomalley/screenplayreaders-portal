<?php

// v1.2 — 2026-06-24 | Notify admins on new partner application
// v1.1 — 2026-06-23 | All form text admin-configurable via partner form settings

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\NotificationHistory;
use App\Models\PartnerSite;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerApplicationController extends Controller
{
    public function show(): View
    {
        $s = Setting::getPartnerFormSettings();
        $percent = (int) $s['partner_form_discount_percent'];

        return view('marketing.partner-apply', [
            'discountPercent' => $percent,
            't'               => self::resolveText($s, $percent),
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        if ($request->filled('website_url')) {
            return redirect()->route('partner-apply')
                ->with('success', true)
                ->with('coupon_code', '');
        }

        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'url'   => 'required|url|max:500',
            'email' => 'required|email|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $code      = 'PRT_' . self::generateSuffix(8);
        $s         = Setting::getPartnerFormSettings();
        $percent   = (int) $s['partner_form_discount_percent'];
        $threshold = (int) $s['partner_form_uptime_threshold'];
        $interval  = (int) $s['partner_form_check_interval_minutes'];

        $site = PartnerSite::create([
            'name'                    => $data['name'],
            'url'                     => $data['url'],
            'contact_email'           => $data['email'],
            'notes'                   => $data['notes'] ?? '',
            'active'                  => false,
            'source'                  => 'application',
            'check_interval_minutes'  => $interval > 0 ? $interval : 1440,
            'coupon_code'             => $code,
            'coupon_discount_type'    => 'percent',
            'coupon_amount'           => $percent,
            'coupon_uptime_threshold' => $threshold > 0 ? $threshold : null,
        ]);

        $adminIds = User::where('role', 'admin')->pluck('id');
        foreach ($adminIds as $adminId) {
            NotificationHistory::log(
                $adminId,
                "New partner application — {$data['name']}",
                "{$data['url']}\n{$data['email']}",
                route('marketing.partner-sites.index')
            );
        }

        return redirect()->route('partner-apply')
            ->with('success', true)
            ->with('coupon_code', $code);
    }

    private static function resolveText(array $s, int $percent): array
    {
        $replace = fn(string $v) => str_replace('{{percent}}', (string) $percent, $v);

        return [
            'heading'           => $replace($s['partner_form_heading']),
            'subheading'        => $replace($s['partner_form_subheading']),
            'copy_url'          => $s['partner_form_copy_url'],
            'dofollow_note'     => $replace($s['partner_form_dofollow_note']),
            'name_label'        => $s['partner_form_name_label'],
            'name_placeholder'  => $s['partner_form_name_placeholder'],
            'url_label'         => $s['partner_form_url_label'],
            'url_placeholder'   => $s['partner_form_url_placeholder'],
            'url_hint'          => $replace($s['partner_form_url_hint']),
            'email_label'       => $s['partner_form_email_label'],
            'email_placeholder' => $s['partner_form_email_placeholder'],
            'notes_label'       => $s['partner_form_notes_label'],
            'notes_placeholder' => $s['partner_form_notes_placeholder'],
            'submit_button'     => $s['partner_form_submit_button'],
            'success_heading'   => $replace($s['partner_form_success_heading']),
            'success_body'      => $replace($s['partner_form_success_body']),
            'success_coupon'    => $replace($s['partner_form_success_coupon']),
        ];
    }

    private static function generateSuffix(int $length): string
    {
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $suffix = '';
        for ($i = 0; $i < $length; $i++) {
            $suffix .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $suffix;
    }
}
