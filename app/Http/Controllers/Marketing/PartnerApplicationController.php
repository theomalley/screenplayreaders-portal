<?php

// v1.0 — 2026-06-23 | Public partner application form (no auth required)

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\PartnerSite;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerApplicationController extends Controller
{
    public function show(): View
    {
        return view('marketing.partner-apply', [
            'discountPercent' => self::discountPercent(),
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

        $code    = 'PRT_' . self::generateSuffix(8);
        $percent = self::discountPercent();

        PartnerSite::create([
            'name'                   => $data['name'],
            'url'                    => $data['url'],
            'contact_email'          => $data['email'],
            'notes'                  => $data['notes'] ?? '',
            'active'                 => false,
            'source'                 => 'application',
            'check_interval_minutes' => 1440,
            'coupon_code'            => $code,
            'coupon_discount_type'   => 'percent',
            'coupon_amount'          => $percent,
        ]);

        return redirect()->route('partner-apply')
            ->with('success', true)
            ->with('coupon_code', $code);
    }

    private static function discountPercent(): int
    {
        return (int) Setting::getValue('partner_coupon_default_percent', 10);
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
