<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApprovalControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeReaderWithPendingBio(): User
    {
        $user = User::factory()->create(['role' => 'reader']);
        $user->readerProfile()->create([
            'initials'    => 'TR',
            'first_name'  => 'Test',
            'last_name'   => 'Reader',
            'bio_pending' => 'Pending bio text',
        ]);

        return $user;
    }

    public function test_admin_can_approve_a_pending_bio(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $reader = $this->makeReaderWithPendingBio();

        $response = $this->actingAs($admin)->postJson("/admin/approvals/bio/{$reader->id}/approve");

        $response->assertOk()->assertJson(['status' => 'approved']);
        $this->assertNull($reader->readerProfile->fresh()->bio_pending);
        $this->assertNotNull($reader->readerProfile->fresh()->bio);
    }

    public function test_editor_cannot_approve_a_pending_bio(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $reader = $this->makeReaderWithPendingBio();

        $response = $this->actingAs($editor)->postJson("/admin/approvals/bio/{$reader->id}/approve");

        $response->assertForbidden();
        $this->assertNotNull($reader->readerProfile->fresh()->bio_pending);
    }

    public function test_reader_cannot_approve_a_pending_bio(): void
    {
        $otherReader = User::factory()->create(['role' => 'reader']);
        $reader      = $this->makeReaderWithPendingBio();

        $response = $this->actingAs($otherReader)->postJson("/admin/approvals/bio/{$reader->id}/approve");

        $response->assertForbidden();
    }

    public function test_admin_can_reject_a_pending_bio_with_a_note(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $reader = $this->makeReaderWithPendingBio();

        $response = $this->actingAs($admin)->postJson("/admin/approvals/bio/{$reader->id}/reject", [
            'note' => 'Please revise',
        ]);

        $response->assertOk()->assertJson(['status' => 'rejected']);
        $reader->readerProfile->refresh();
        $this->assertNull($reader->readerProfile->bio_pending);
        $this->assertSame('Please revise', $reader->readerProfile->bio_rejection_note);
    }

    public function test_editor_cannot_reject_a_pending_bio(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $reader = $this->makeReaderWithPendingBio();

        $response = $this->actingAs($editor)->postJson("/admin/approvals/bio/{$reader->id}/reject");

        $response->assertForbidden();
    }
}
