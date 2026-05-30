<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('followup_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('order_number', 64)->index();
            $table->json('assignment_ids');           // array of assignment IDs covered by this token
            $table->string('customer_email')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('followup_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('followup_token_id')->constrained('followup_tokens')->cascadeOnDelete();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->string('order_number', 64)->index();
            $table->text('customer_questions')->nullable();   // raw customer submission
            $table->text('edited_questions')->nullable();     // admin-approved version shown to reader
            $table->text('reader_response')->nullable();      // reader's raw response
            $table->text('edited_response')->nullable();      // admin-edited response sent to customer
            $table->string('status', 20)->default('pending'); // pending|unanswered|answered|complete
            $table->timestamp('unanswered_at')->nullable();   // when status hit unanswered — 10-day clock starts
            $table->timestamps();
        });

        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->boolean('email_notify_followup')->default(false)->after('email_notify_requests');
            $table->boolean('sms_notify_followup')->default(false)->after('sms_notify_requests');
        });
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->dropColumn(['email_notify_followup', 'sms_notify_followup']);
        });
        Schema::dropIfExists('followup_questions');
        Schema::dropIfExists('followup_tokens');
    }
};
