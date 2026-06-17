<?php

// v1.1 — 2026-06-17 | Add invoicePdf() — generate a Google Doc invoice from a Woo order and stream as PDF download
// v1.0 — 2026-05-26 | WooCommerce order browser — list, detail, refund, resend email (admin + editor)

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\GoogleDocsService;
use App\Services\InvoiceService;
use App\Services\WooCommerceService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

class WooOrderController extends Controller
{
    private static array $STATUSES = [
        'any'        => 'All Statuses',
        'pending'    => 'Pending',
        'processing' => 'Processing',
        'on-hold'    => 'On Hold',
        'completed'  => 'Completed',
        'cancelled'  => 'Cancelled',
        'refunded'   => 'Refunded',
        'failed'     => 'Failed',
    ];

    public function index(Request $request, WooCommerceService $woo)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $q      = trim((string) $request->input('q', ''));
        $status = $request->input('status', 'any');
        $page   = max(1, (int) $request->input('page', 1));

        if (! array_key_exists($status, self::$STATUSES)) {
            $status = 'any';
        }

        $params = ['page' => $page];
        if ($q !== '') {
            $params['search'] = $q;
        }
        if ($status !== 'any') {
            $params['status'] = $status;
        }

        try {
            $result = $woo->getOrders($params);
        } catch (RuntimeException $e) {
            return view('woo-orders.index', [
                'orders'      => [],
                'total'       => 0,
                'totalPages'  => 0,
                'page'        => 1,
                'q'           => $q,
                'status'      => $status,
                'statuses'    => self::$STATUSES,
                'error'       => $e->getMessage(),
            ]);
        }

        $orders = array_filter($result['orders'], fn($o) => (float)($o['total'] ?? 0) > 0);

        return view('woo-orders.index', [
            'orders'     => array_values($orders),
            'total'      => $result['total'],
            'totalPages' => $result['total_pages'],
            'page'       => $page,
            'q'          => $q,
            'status'     => $status,
            'statuses'   => self::$STATUSES,
            'error'      => null,
        ]);
    }

    public function show(int $id, WooCommerceService $woo)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        try {
            $order = $woo->getOrder($id);
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (RuntimeException $e) {
            return back()->withErrors(['api' => $e->getMessage()]);
        }

        return view('woo-orders.show', [
            'order'            => $order,
            'refundableAmount' => WooCommerceService::refundableAmount($order),
        ]);
    }

    public function refund(Request $request, int $id, WooCommerceService $woo)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        // Fetch the live order to get the current refundable amount
        try {
            $order = $woo->getOrder($id);
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (RuntimeException $e) {
            return back()->withErrors(['api' => $e->getMessage()]);
        }

        $maxRefundable = WooCommerceService::refundableAmount($order);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', "max:{$maxRefundable}"],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $woo->createRefund($id, (float) $validated['amount'], (string) ($validated['reason'] ?? ''));
        } catch (RuntimeException $e) {
            return back()->withErrors(['refund' => $e->getMessage()]);
        }

        return redirect()->route('woo-orders.show', $id)
            ->with('success', 'Refund of $' . number_format((float) $validated['amount'], 2) . ' issued successfully.');
    }

    public function resendEmail(Request $request, int $id, WooCommerceService $woo)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $validated = $request->validate([
            'test_email' => ['nullable', 'email', 'max:255'],
        ]);

        $testEmail = $validated['test_email'] ?? null;

        try {
            $woo->resendEmail($id, $testEmail);
        } catch (RuntimeException $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }

        $dest = $testEmail ? "test address ({$testEmail})" : 'customer';
        return back()->with('success', "Order email resent to {$dest}.");
    }

    public function invoicePdf(int $id, WooCommerceService $woo, GoogleDocsService $docs)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        try {
            $order = $woo->getOrder($id);
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (RuntimeException $e) {
            return back()->withErrors(['api' => $e->getMessage()]);
        }

        $b         = $order['billing'] ?? [];
        $name      = trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? ''));
        $addrLine1 = trim(implode(', ', array_filter([$b['address_1'] ?? '', $b['address_2'] ?? ''])));
        $addrLine2 = trim(implode(', ', array_filter([
            $b['city'] ?? '',
            $b['state'] ?? '',
            $b['postcode'] ?? '',
            $b['country'] ?? '',
        ])));

        $placeholders = [
            '{{SR_ADDRESS}}'    => Setting::getValue('sr_invoice_address', ''),
            '{{INVOICENUMBER}}' => (string) ($order['number'] ?? $id),
            '{{DATE}}'          => now()->format('F j, Y'),
            '{{name}}'          => $name,
            '{{company}}'       => '',
            '{{addressline1}}'  => $addrLine1,
            '{{addressline2}}'  => $addrLine2,
            '{{notes}}'         => '',
            '{{TOTAL}}'         => number_format((float) ($order['total'] ?? 0), 2),
            '{{URL}}'           => '',
        ];

        $lineItems = $order['line_items'] ?? [];
        for ($i = 1; $i <= 8; $i++) {
            $item = $lineItems[$i - 1] ?? null;
            $qty  = $item ? max(1, (int) ($item['quantity'] ?? 1)) : 0;
            $placeholders["{{service{$i}}}"]    = $item ? ($item['name'] ?? '') : '';
            $placeholders["{{title{$i}}}"]      = '';
            $placeholders["{{price{$i}}}"]      = $item ? number_format((float) ($item['total'] ?? 0) / $qty, 2) : '';
            $placeholders["{{qty{$i}}}"]        = $item ? (string) $qty : '';
            $placeholders["{{FINALPRICE{$i}}}"] = $item ? number_format((float) ($item['total'] ?? 0), 2) : '';
        }

        try {
            $bytes = $docs->generatePdfBytesAndCleanup(InvoiceService::INVOICE_TEMPLATE_ID, $placeholders);
        } catch (RuntimeException $e) {
            return back()->withErrors(['api' => 'Invoice PDF generation failed: ' . $e->getMessage()]);
        }

        $filename = 'Invoice — Order #' . ($order['number'] ?? $id) . ($name ? ' — ' . $name : '') . '.pdf';

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => strlen($bytes),
        ]);
    }
}
