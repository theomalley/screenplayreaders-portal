<?php

// v1.0 — 2026-05-26 | WooCommerce order browser — list, detail, refund, resend email (admin + editor)

namespace App\Http\Controllers;

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

        return view('woo-orders.index', [
            'orders'     => $result['orders'],
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
        return back()->with('success', "Receipt email sent to {$dest}.");
    }
}
