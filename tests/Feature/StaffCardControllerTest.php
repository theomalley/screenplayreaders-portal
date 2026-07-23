<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StaffCardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeReader(): User
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        return $reader;
    }

    public function test_admin_and_editor_can_view_the_staff_card(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);
        $reader = $this->makeReader();

        $this->actingAs($admin)->get("/staff/{$reader->id}/card")->assertOk();
        $this->actingAs($editor)->get("/staff/{$reader->id}/card")->assertOk();
    }

    public function test_reader_cannot_view_the_staff_card(): void
    {
        $reader      = $this->makeReader();
        $otherReader = $this->makeReader();

        $this->actingAs($reader)->get("/staff/{$otherReader->id}/card")->assertForbidden();
    }

    public function test_any_authenticated_user_can_view_the_reader_card(): void
    {
        $reader      = $this->makeReader();
        $otherReader = $this->makeReader();
        $admin       = User::factory()->create(['role' => 'admin']);

        $this->actingAs($reader)->get("/staff/{$otherReader->id}/reader-card")->assertOk();
        $this->actingAs($admin)->get("/staff/{$reader->id}/reader-card")->assertOk();
    }

    public function test_admin_and_editor_can_draft_an_email_to_a_reader(): void
    {
        Http::fake(fn () => Http::response(['token_type' => 'Bearer', 'access_token' => 'x', '_embedded' => ['mailboxes' => [['id' => 1]]], 'id' => 999], 200));

        $admin  = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);
        $reader = $this->makeReader();

        $this->assertNotEquals(403, $this->actingAs($admin)->get("/staff/{$reader->id}/draft-email")->getStatusCode());
        $this->assertNotEquals(403, $this->actingAs($editor)->get("/staff/{$reader->id}/draft-email")->getStatusCode());
    }

    public function test_reader_cannot_draft_an_email(): void
    {
        $reader      = $this->makeReader();
        $otherReader = $this->makeReader();

        $this->actingAs($reader)->get("/staff/{$otherReader->id}/draft-email")->assertForbidden();
    }

    public function test_cannot_draft_an_email_to_an_admin(): void
    {
        $admin      = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get("/staff/{$otherAdmin->id}/draft-email")->assertForbidden();
    }
}
