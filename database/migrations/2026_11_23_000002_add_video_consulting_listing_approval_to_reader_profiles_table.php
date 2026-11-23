<?php

// v1.0 — 2026-11-23 | Reader-submitted video_consulting_blurb_header/video_consulting_blurb/
//                      video_consulting_note now go through admin approval before going live
//                      (same reader/editor-vs-admin approval gate as bio/photo/about_photo),
//                      edited and reviewed together as one unit since they're all submitted
//                      from the same "Listing" form — see
//                      ProfileController::updateVideoConsultingListing()/
//                      AdminApprovalController::approveVideoConsultingListing().

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->json('video_consulting_listing_pending')->nullable()->after('video_consulting_blurb');
            $table->text('video_consulting_listing_rejection_note')->nullable()->after('video_consulting_listing_pending');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn(['video_consulting_listing_pending', 'video_consulting_listing_rejection_note']);
        });
    }
};
