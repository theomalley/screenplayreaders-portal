<?php

use App\Http\Controllers\Api\HelpScoutConversationController;
use App\Http\Controllers\Api\IncomingAssignmentController;
use App\Http\Controllers\Api\OrderRevenueController;
use App\Http\Controllers\Api\ReadersController;
use Illuminate\Support\Facades\Route;

// PORTAL INTEGRATION: called by WordPress sr-upload-system.php after customer checkout + script upload
Route::post('/incoming-assignment', [IncomingAssignmentController::class, 'store']);

// PORTAL INTEGRATION: called by WordPress sr-upload-system.php to populate the reader dropdown
Route::get('/readers', [ReadersController::class, 'index']);

// ZAPIER INTEGRATION: called by sr-orders zap after HelpScout ticket is created — stores order → conversation ID
Route::post('/helpscout-conversation', [HelpScoutConversationController::class, 'store']);

// WOOCOMMERCE INTEGRATION: called by woo_order-financials.php (priority 15) on order completion — stores financials
Route::post('/order-revenue', [OrderRevenueController::class, 'store']);
