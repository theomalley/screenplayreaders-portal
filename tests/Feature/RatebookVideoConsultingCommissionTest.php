<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin-only "Editors can earn commission on video consulting orders" checkbox on the
 * Ratebook page — editor/reader can view Ratebook itself but not this control or its save route.
 */
class RatebookVideoConsultingCommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_toggle_the_setting(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->patch('/ratebook/video-consulting-commission', [
            'video_consulting_editor_commission_enabled' => '1',
        ]);
        $response->assertRedirect();
        $this->assertSame('1', Setting::getValue('video_consulting_editor_commission_enabled'));

        $response = $this->actingAs($admin)->patch('/ratebook/video-consulting-commission', [
            // omitted => unchecked
        ]);
        $response->assertRedirect();
        $this->assertSame('0', Setting::getValue('video_consulting_editor_commission_enabled'));
    }

    public function test_setting_defaults_to_disabled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/ratebook')->assertOk();

        $this->assertSame('0', Setting::getValue('video_consulting_editor_commission_enabled', '0'));
    }

    public function test_editor_is_forbidden_from_toggling_the_setting(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $response = $this->actingAs($editor)->patch('/ratebook/video-consulting-commission', [
            'video_consulting_editor_commission_enabled' => '0',
        ]);

        $response->assertStatus(403);
    }

    public function test_reader_is_forbidden_from_toggling_the_setting(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);

        $response = $this->actingAs($reader)->patch('/ratebook/video-consulting-commission', [
            'video_consulting_editor_commission_enabled' => '0',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_sees_the_toggle_on_the_ratebook_page_but_editor_does_not(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($admin)->get('/ratebook')
            ->assertOk()
            ->assertSee('Video Consulting Commission');

        $this->actingAs($editor)->get('/ratebook')
            ->assertOk()
            ->assertDontSee('Video Consulting Commission');
    }
}
