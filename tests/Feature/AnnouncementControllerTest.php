<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeAnnouncement(User $createdBy): Announcement
    {
        return Announcement::create([
            'body'       => 'Test announcement',
            'created_by' => $createdBy->id,
        ]);
    }

    public function test_admin_and_editor_can_post_an_announcement(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($admin)->post('/announcements', ['body' => 'Hello'])->assertRedirect();
        $this->actingAs($editor)->post('/announcements', ['body' => 'Hello'])->assertRedirect();
    }

    public function test_reader_cannot_post_an_announcement(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($reader)->post('/announcements', ['body' => 'Hello'])->assertForbidden();
    }

    public function test_admin_can_update_any_announcement(): void
    {
        $admin       = User::factory()->create(['role' => 'admin']);
        $editor      = User::factory()->create(['role' => 'editor']);
        $announcement = $this->makeAnnouncement($editor);

        $this->actingAs($admin)->put("/announcements/{$announcement->id}", ['body' => 'Updated'])->assertRedirect();
    }

    public function test_editor_can_update_only_their_own_announcement(): void
    {
        $editor      = User::factory()->create(['role' => 'editor']);
        $otherEditor = User::factory()->create(['role' => 'editor']);
        $own         = $this->makeAnnouncement($editor);
        $others      = $this->makeAnnouncement($otherEditor);

        $this->actingAs($editor)->put("/announcements/{$own->id}", ['body' => 'Updated'])->assertRedirect();
        $this->actingAs($editor)->put("/announcements/{$others->id}", ['body' => 'Updated'])->assertForbidden();
    }

    public function test_reader_cannot_update_an_announcement(): void
    {
        $reader      = User::factory()->create(['role' => 'reader']);
        $admin       = User::factory()->create(['role' => 'admin']);
        $announcement = $this->makeAnnouncement($admin);

        $this->actingAs($reader)->put("/announcements/{$announcement->id}", ['body' => 'x'])->assertForbidden();
    }

    public function test_admin_and_editor_can_delete_an_announcement(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);
        $a1     = $this->makeAnnouncement($admin);
        $a2     = $this->makeAnnouncement($admin);

        $this->actingAs($admin)->delete("/announcements/{$a1->id}")->assertRedirect();
        $this->actingAs($editor)->delete("/announcements/{$a2->id}")->assertRedirect();
    }

    public function test_reader_cannot_delete_an_announcement(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $admin  = User::factory()->create(['role' => 'admin']);
        $announcement = $this->makeAnnouncement($admin);

        $this->actingAs($reader)->delete("/announcements/{$announcement->id}")->assertForbidden();
    }

    public function test_any_authenticated_user_can_view_history_mark_read_and_dismiss(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $admin  = User::factory()->create(['role' => 'admin']);
        $announcement = $this->makeAnnouncement($admin);

        $this->actingAs($reader)->get('/announcements/history')->assertOk();
        $this->actingAs($reader)->post("/announcements/{$announcement->id}/read")->assertNoContent();
        $this->actingAs($reader)->post("/announcements/{$announcement->id}/dismiss")->assertNoContent();
    }
}
