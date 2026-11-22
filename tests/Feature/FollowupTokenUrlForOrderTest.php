<?php

namespace Tests\Feature;

use App\Models\FollowupToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the FollowupToken::urlForOrder() fix (2026-11-22): reusing an
 * existing unexpired token must merge in newly-passed assignment IDs, not silently keep
 * the stale set from when the token was first created.
 */
class FollowupTokenUrlForOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reusing_an_existing_token_merges_in_new_assignment_ids(): void
    {
        FollowupToken::urlForOrder('ORD-1', [101]);

        $token = FollowupToken::where('order_number', 'ORD-1')->first();
        $this->assertSame([101], $token->assignment_ids);

        // A later call (e.g. a second reader's assignment completing) passes a new ID.
        FollowupToken::urlForOrder('ORD-1', [102]);

        $this->assertSame([101, 102], $token->fresh()->assignment_ids);

        // Still only one token row for the order — reused, not duplicated.
        $this->assertSame(1, FollowupToken::where('order_number', 'ORD-1')->count());
    }

    public function test_calling_again_with_no_new_ids_does_not_touch_the_existing_set(): void
    {
        FollowupToken::urlForOrder('ORD-2', [201, 202]);

        FollowupToken::urlForOrder('ORD-2', []);

        $token = FollowupToken::where('order_number', 'ORD-2')->first();
        $this->assertSame([201, 202], $token->assignment_ids);
    }
}
