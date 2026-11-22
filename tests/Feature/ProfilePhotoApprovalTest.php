<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression coverage for the ProfileController::uploadPhoto() fix (2026-11-22):
 * a reader/editor's "Reader Icon" upload used to go straight to the live `photo`
 * column with no admin review, unlike the sibling bio/about-photo uploads.
 */
class ProfilePhotoApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_readers_photo_upload_goes_to_pending_not_live(): void
    {
        Storage::fake('public');

        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $this->actingAs($reader)
            ->post('/profile/photo', ['photo' => UploadedFile::fake()->image('photo.jpg', 600, 600)])
            ->assertRedirect();

        $profile = $reader->fresh('readerProfile')->readerProfile;

        $this->assertNull($profile->photo);
        $this->assertNotNull($profile->photo_pending);
    }

    public function test_admins_photo_upload_goes_live_immediately(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->editorProfile()->create(['initials' => 'AD', 'first_name' => 'Test', 'last_name' => 'Admin']);

        $this->actingAs($admin)
            ->post('/profile/photo', ['photo' => UploadedFile::fake()->image('photo.jpg', 600, 600)])
            ->assertRedirect();

        $profile = $admin->fresh('editorProfile')->editorProfile;

        $this->assertNotNull($profile->photo);
        $this->assertNull($profile->photo_pending);
    }
}
