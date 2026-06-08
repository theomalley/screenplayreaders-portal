<?php

// v1.0 — 2026-06-08 | Edit the base email HTML template stored in settings (key: email_base_template)

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class BaseEmailTemplateController extends Controller
{
    private const SETTING_KEY = 'email_base_template';

    public function edit(): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $template = Setting::getValue(self::SETTING_KEY)
            ?? file_get_contents(resource_path('views/marketing/email-campaigns/partials/email-html.blade.php'));

        return view('marketing.base-email-template.edit', compact('template'));
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate(['template' => 'required|string']);
        Setting::setValue(self::SETTING_KEY, $request->input('template'));

        return back()->with('success', 'Base email template saved.');
    }

    public function preview(Request $request): Response
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $template = $request->input('template', '');
        $isLive   = $request->boolean('live');

        $data = [
            'subjectLine'     => 'Sample Campaign',
            'preheader'       => 'This is a sample preheader — it shows in the inbox snippet.',
            'headlineTop'     => 'Exclusive Offer for You',
            'paragraphTop1'   => 'We wanted to reach out with a special offer just for you. As a valued client, here\'s an exclusive discount on your next order.',
            'paragraphTop2'   => 'Use the code below at checkout.',
            'couponCode'      => 'SR-SAMPLE25',
            'couponExpiry'    => 'July 15, 2026',
            'couponFinePrint' => 'Coupon expires July 15, 2026',
            'url1'            => 'https://screenplayreaders.com',
            'headlineBottom'  => '',
            'paragraphBottom' => '',
            'imageUrl'        => '',
            'preview'         => !$isLive,
        ];

        try {
            $html = \Blade::render($template, $data);
        } catch (\Throwable $e) {
            $html = '<pre style="color:red;padding:1rem;font-family:monospace;">Template error: '
                . htmlspecialchars($e->getMessage()) . '</pre>';
        }

        return response($html)->header('Content-Type', 'text/html');
    }

    public function reset(): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        Setting::where('key', self::SETTING_KEY)->delete();

        return back()->with('success', 'Base template reset to code default.');
    }
}
