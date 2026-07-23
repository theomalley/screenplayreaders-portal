<?php

use App\Http\Controllers\Api\HelpScoutConversationController;
use App\Http\Controllers\Api\HelpScoutWebhookController;
use App\Http\Controllers\Api\IncomingAssignmentController;
use App\Http\Controllers\Api\OrderRevenueController;
use App\Http\Controllers\Api\ReadCreditApiController;
use App\Http\Controllers\Api\ReadersController;
use App\Http\Controllers\Api\StaffProfileController;
use App\Http\Controllers\Api\UploadSettingsController;
use Illuminate\Support\Facades\Route;

// PORTAL INTEGRATION: called by WordPress sr-upload-system.php after customer checkout + script upload
Route::post('/incoming-assignment', [IncomingAssignmentController::class, 'store'])->middleware(['throttle:30,1', 'portal.secret']);

// PORTAL INTEGRATION: called by WordPress sr-upload-system.php to populate the reader dropdown
Route::get('/readers', [ReadersController::class, 'index'])->middleware(['throttle:60,1', 'portal.secret']);

// PORTAL INTEGRATION: called by WordPress sr-upload-system.php to fetch block-reader limit settings
Route::get('/upload-settings', [UploadSettingsController::class, 'index'])->middleware(['throttle:60,1', 'portal.secret']);

// ZAPIER INTEGRATION: called by sr-orders zap after HelpScout ticket is created — stores order → conversation ID
Route::post('/helpscout-conversation', [HelpScoutConversationController::class, 'store'])->middleware(['throttle:30,1', 'portal.secret']);

// HELPSCOUT INTEGRATION: webhook fired by HelpScout when an agent reply is created/sent —
// used to stamp helpscout_sent_at for the upload→delivery turnaround stat
// NOTE: authenticated via HMAC signature (X-HelpScout-Signature), not the shared portal
// secret — this is a different provider/mechanism, so 'portal.secret' does not apply here.
Route::post('/helpscout-webhook', [HelpScoutWebhookController::class, 'store'])->middleware('throttle:60,1');

// WOOCOMMERCE INTEGRATION: called by woo_order-financials.php (priority 15) on order completion — stores financials
Route::post('/order-revenue', [OrderRevenueController::class, 'store'])->middleware(['throttle:30,1', 'portal.secret']);

// WOOCOMMERCE INTEGRATION: called by woo_budgeting.php on order completion for product 55672
// Receives GF Form 9 data, runs budget calculation engine, dispatches file generation
Route::post('/budget-order', [\App\Http\Controllers\Api\BudgetWebhookController::class, 'store'])->middleware(['throttle:30,1', 'portal.secret']);

// WOOCOMMERCE INTEGRATION: called by woo_scriptregistration.php on order completion for product 55560
// Receives registration payloads, generates certificates, emails customers
Route::post('/script-registration', [\App\Http\Controllers\Api\ScriptRegistrationWebhookController::class, 'store'])->middleware(['throttle:30,1', 'portal.secret']);

// PUBLIC STAFF PROFILES: called by WordPress shortcodes to render staff bios and photos on the website
Route::get('/staff/{user}', [StaffProfileController::class, 'show'])->middleware('throttle:60,1');

// READ CREDITS: Notes-Only package credit management — called by WordPress
// NOTE: 'show' (status lookup) is intentionally public/unauthenticated — the upload
// token itself is the credential a customer's link carries. The other four actions
// are portal-secret-gated the same as every other WordPress-facing endpoint above.
Route::post('/read-credits', [ReadCreditApiController::class, 'store'])->middleware(['throttle:10,1', 'portal.secret']);
Route::get('/read-credits/{token}', [ReadCreditApiController::class, 'show'])->middleware('throttle:60,1');
Route::post('/read-credits/{token}/redeem', [ReadCreditApiController::class, 'redeem'])->middleware(['throttle:10,1', 'portal.secret']);
Route::post('/read-credits/{token}/set-coupon', [ReadCreditApiController::class, 'setCoupon'])->middleware(['throttle:10,1', 'portal.secret']);
Route::get('/read-credits-expired-pending-coupon', [ReadCreditApiController::class, 'expiredPendingCoupon'])->middleware(['throttle:10,1', 'portal.secret']);
