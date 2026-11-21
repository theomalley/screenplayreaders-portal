<?php

namespace Tests\Feature;

use App\Models\EditorProductCommission;
use App\Models\EditorProfile;
use App\Models\Setting;
use App\Models\User;
use App\Services\EditorCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin-level master switch (Ratebook, video_consulting_editor_commission_enabled) that
 * overrides any individual editor's own per-product EditorProductCommission config for the
 * video consulting Woo product (58573) specifically.
 */
class EditorCommissionServiceVideoConsultingTest extends TestCase
{
    use RefreshDatabase;

    private const VIDEO_CONSULTING_PRODUCT_ID = 58573;

    private function editorProfileWithVideoConsultingCommissionEnabled(): EditorProfile
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $profile = $editor->editorProfile()->create([
            'initials' => 'XX', 'first_name' => 'Test', 'last_name' => 'Editor', 'editor_commission' => 10.0,
        ]);
        EditorProductCommission::create([
            'editor_profile_id'  => $profile->id,
            'woo_product_id'     => self::VIDEO_CONSULTING_PRODUCT_ID,
            'product_label'      => 'Video Consulting',
            'commission_enabled' => true,
        ]);

        return $profile;
    }

    private function lineItemsJsonFor(float $lineTotal): string
    {
        return json_encode([[
            'product_id'          => self::VIDEO_CONSULTING_PRODUCT_ID,
            'line_total'          => $lineTotal,
            'commission_eligible' => false, // the theme's real default for this product today
        ]]);
    }

    public function test_master_switch_defaults_to_off_overriding_the_editors_own_enabled_config(): void
    {
        $profile = $this->editorProfileWithVideoConsultingCommissionEnabled();

        $commission = (new EditorCommissionService())->calculate($profile, $this->lineItemsJsonFor(500), 500);

        $this->assertSame(0.0, $commission);
    }

    public function test_commission_computed_when_editor_override_enabled_and_master_switch_turned_on(): void
    {
        Setting::setValue('video_consulting_editor_commission_enabled', '1');
        $profile = $this->editorProfileWithVideoConsultingCommissionEnabled();

        $commission = (new EditorCommissionService())->calculate($profile, $this->lineItemsJsonFor(500), 500);

        $this->assertNotNull($commission);
        $this->assertGreaterThan(0, $commission);
    }

    public function test_master_switch_does_not_affect_other_products(): void
    {
        Setting::setValue('video_consulting_editor_commission_enabled', '0');

        $editor = User::factory()->create(['role' => 'editor']);
        $profile = $editor->editorProfile()->create([
            'initials' => 'YY', 'first_name' => 'Test', 'last_name' => 'Editor', 'editor_commission' => 10.0,
        ]);
        EditorProductCommission::create([
            'editor_profile_id'  => $profile->id,
            'woo_product_id'     => 54911,
            'product_label'      => '1 Reader',
            'commission_enabled' => true,
        ]);

        $lineItems = json_encode([[
            'product_id'          => 54911,
            'line_total'          => 500,
            'commission_eligible' => true,
        ]]);

        $commission = (new EditorCommissionService())->calculate($profile, $lineItems, 500);

        $this->assertNotNull($commission);
        $this->assertGreaterThan(0, $commission);
    }

    public function test_video_consulting_commission_enabled_defaults_to_false(): void
    {
        $this->assertFalse(EditorCommissionService::videoConsultingCommissionEnabled());
    }

    public function test_video_consulting_commission_cap_clamps_the_computed_amount(): void
    {
        Setting::setValue('video_consulting_editor_commission_enabled', '1');
        $profile = $this->editorProfileWithVideoConsultingCommissionEnabled();
        $profile->update(['video_consulting_commission_cap' => 20]);

        // 10% of 500 = $50, which the cap should clamp down to $20.
        $commission = (new EditorCommissionService())->calculate($profile, $this->lineItemsJsonFor(500), 500);

        $this->assertSame(20.0, $commission);
    }

    public function test_video_consulting_commission_cap_does_not_reduce_amounts_already_under_it(): void
    {
        Setting::setValue('video_consulting_editor_commission_enabled', '1');
        $profile = $this->editorProfileWithVideoConsultingCommissionEnabled();
        $profile->update(['video_consulting_commission_cap' => 100]);

        // 10% of 500 = $50, under the $100 cap, so it should be unaffected.
        $commission = (new EditorCommissionService())->calculate($profile, $this->lineItemsJsonFor(500), 500);

        $this->assertSame(50.0, $commission);
    }

    public function test_null_video_consulting_commission_cap_leaves_commission_uncapped(): void
    {
        Setting::setValue('video_consulting_editor_commission_enabled', '1');
        $profile = $this->editorProfileWithVideoConsultingCommissionEnabled();

        $this->assertNull($profile->video_consulting_commission_cap);

        $commission = (new EditorCommissionService())->calculate($profile, $this->lineItemsJsonFor(500), 500);

        $this->assertSame(50.0, $commission);
    }

    public function test_video_consulting_commission_cap_does_not_affect_other_products_in_the_same_order(): void
    {
        Setting::setValue('video_consulting_editor_commission_enabled', '1');
        $profile = $this->editorProfileWithVideoConsultingCommissionEnabled();
        $profile->update(['video_consulting_commission_cap' => 20]);

        EditorProductCommission::create([
            'editor_profile_id'  => $profile->id,
            'woo_product_id'     => 54911,
            'product_label'      => '1 Reader',
            'commission_enabled' => true,
        ]);

        // Video consulting line: $500 (10% = $50, capped to $20). Other product: $500 (10% = $50, uncapped).
        $lineItems = json_encode([
            ['product_id' => self::VIDEO_CONSULTING_PRODUCT_ID, 'line_total' => 500, 'commission_eligible' => false],
            ['product_id' => 54911, 'line_total' => 500, 'commission_eligible' => true],
        ]);

        $commission = (new EditorCommissionService())->calculate($profile, $lineItems, 1000);

        // $20 (capped video consulting) + $50 (uncapped other product) = $70.
        $this->assertSame(70.0, $commission);
    }
}
