<?php

// v1.0 — 2026-11-19 | Record which of the reader's own video-consulting discount codes (if
//                      any) was used on this booking, and the realized dollar amount it took
//                      off the reader's pay_rate — for reporting/reader-pay reconciliation.
//                      pay_rate itself already carries the post-discount amount (see
//                      VideoConsultingBookingController::createBooking()); these columns are
//                      just the audit trail of why.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('video_consulting_coupon_code_used')->nullable()->after('video_consulting_customer_email');
            $table->decimal('video_consulting_discount_amount', 8, 2)->nullable()->after('video_consulting_coupon_code_used');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['video_consulting_coupon_code_used', 'video_consulting_discount_amount']);
        });
    }
};
