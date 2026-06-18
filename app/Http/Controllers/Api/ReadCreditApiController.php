<?php

// v1.0 — 2026-06-18 | API endpoints for Notes-Only read credit packages: create (from WP),
//                     check status (from WP upload form), use credit + create assignment.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\UploadScriptToDrive;
use App\Models\Assignment;
use App\Models\ReadCreditPackage;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReadCreditApiController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! $this->authorised($request)) {
            return response()->json(['error' => 'Unauthorised.'], 401);
        }

        $data = $request->validate([
            'customer_email'   => 'required|email|max:255',
            'customer_name'    => 'required|string|max:255',
            'woo_order_number' => 'required|string|max:64',
            'product_id'       => 'required|integer',
            'credits'          => 'required|integer|in:5,10',
        ]);

        $existing = ReadCreditPackage::where('woo_order_number', $data['woo_order_number'])->first();
        if ($existing) {
            return response()->json([
                'status'       => 'already_exists',
                'upload_token' => $existing->upload_token,
                'upload_url'   => $existing->uploadUrl(),
            ], 200);
        }

        $pkg = ReadCreditPackage::create([
            'customer_email'   => $data['customer_email'],
            'customer_name'    => $data['customer_name'],
            'woo_order_number' => $data['woo_order_number'],
            'product_id'       => $data['product_id'],
            'credits_purchased' => $data['credits'],
            'credits_remaining' => $data['credits'],
            'status'           => ReadCreditPackage::STATUS_ACTIVE,
            'expires_at'       => now()->addYear(),
        ]);

        Log::info('ReadCredit: provisioned', [
            'order_number' => $data['woo_order_number'],
            'credits'      => $data['credits'],
            'token'        => $pkg->upload_token,
        ]);

        return response()->json([
            'status'       => 'created',
            'upload_token' => $pkg->upload_token,
            'upload_url'   => $pkg->uploadUrl(),
        ], 201);
    }

    public function show(string $token): JsonResponse
    {
        $pkg = ReadCreditPackage::where('upload_token', $token)->first();

        if (! $pkg) {
            return response()->json(['error' => 'Invalid token.'], 404);
        }

        $pkg->checkExpiration();

        return response()->json([
            'credits_remaining' => $pkg->credits_remaining,
            'credits_purchased' => $pkg->credits_purchased,
            'expires_at'        => $pkg->expires_at->toIso8601String(),
            'expires_at_human'  => $pkg->expires_at->format('F j, Y'),
            'customer_name'     => $pkg->customer_name,
            'customer_email'    => $pkg->customer_email,
            'status'            => $pkg->status,
            'package_label'     => $pkg->packageLabel(),
        ]);
    }

    public function redeem(Request $request, string $token): JsonResponse
    {
        if (! $this->authorised($request)) {
            return response()->json(['error' => 'Unauthorised.'], 401);
        }

        $data = $request->validate([
            'script_title' => 'required|string|max:255',
            'writer_name'  => 'nullable|string|max:255',
            'page_count'   => 'nullable|integer|min:1',
            'script'       => 'required|file|max:5120',
        ]);

        $pkg = ReadCreditPackage::where('upload_token', $token)->first();

        if (! $pkg) {
            return response()->json(['error' => 'Invalid token.'], 404);
        }

        return DB::transaction(function () use ($pkg, $data, $request) {
            $pkg = ReadCreditPackage::where('id', $pkg->id)->lockForUpdate()->first();
            $pkg->checkExpiration();

            if (! $pkg->isUsable()) {
                $reason = match ($pkg->status) {
                    ReadCreditPackage::STATUS_EXPIRED   => 'Credits have expired.',
                    ReadCreditPackage::STATUS_EXHAUSTED => 'No credits remaining.',
                    default                             => 'Credits are not available.',
                };
                return response()->json(['error' => $reason], 422);
            }

            $usageNumber = $pkg->credits_purchased - $pkg->credits_remaining + 1;
            $orderNumber = 'CREDIT-' . $pkg->woo_order_number . '-' . $usageNumber;

            $rates   = Setting::ratesForForms();
            $payRate = (float) ($rates['rate_sr_notes_only'] ?? 0);

            $assignment = Assignment::create([
                'order_number'    => $orderNumber,
                'vendor'          => 'sr',
                'assignment_type' => 'notes_only',
                'script_title'    => $data['script_title'],
                'writer_name'     => $data['writer_name'] ?? $pkg->customer_name,
                'page_count'      => $data['page_count'] ?? null,
                'rush'            => false,
                'pay_rate'        => $payRate,
                'status'          => Assignment::STATUS_INCOMING,
            ]);

            $clientExt   = strtolower($request->file('script')->getClientOriginalExtension());
            $storageName = Str::random(40) . ($clientExt !== '' ? '.' . $clientExt : '');
            $storagePath = $request->file('script')->storeAs('incoming-scripts', $storageName, 'local');

            if ($storagePath === false) {
                throw new \RuntimeException('File storage failed.');
            }

            UploadScriptToDrive::dispatch($assignment->id, $storagePath);

            $pkg->useCredit();

            Log::info('ReadCredit: credit used', [
                'token'       => $pkg->upload_token,
                'order'       => $orderNumber,
                'remaining'   => $pkg->credits_remaining,
                'assignment'  => $assignment->id,
            ]);

            return response()->json([
                'status'            => 'success',
                'credits_remaining' => $pkg->credits_remaining,
                'assignment_id'     => $assignment->id,
                'order_number'      => $orderNumber,
            ]);
        });
    }

    private function authorised(Request $request): bool
    {
        $secret = config('services.portal.webhook_secret');

        return ! empty($secret) && hash_equals($secret, $request->bearerToken() ?? '');
    }
}
