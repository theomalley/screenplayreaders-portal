<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared bearer-secret check for the WordPress-theme-facing webhook endpoints
 * in routes/api.php (incoming-assignment, readers, upload-settings,
 * helpscout-conversation, order-revenue, budget-order, script-registration,
 * read-credits*). Replaces an identical private authorised()/authorized()
 * method that was copy-pasted into 8 separate controllers.
 */
class VerifyPortalWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.portal.webhook_secret');

        if (empty($secret) || ! hash_equals($secret, $request->bearerToken() ?? '')) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
