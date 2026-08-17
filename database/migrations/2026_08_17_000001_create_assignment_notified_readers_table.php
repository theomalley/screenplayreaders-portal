<?php

// Ledger of which readers have already been emailed about which order — lets
// ReaderNotificationService skip a reader who was already notified for this
// order_number by a sibling Assignment row (e.g. the two notes_only slots on a
// 3-reader order) or by a later re-notify trigger (EscalateTierTimeouts).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_notified_readers', function (Blueprint $table) {
            $table->id();
            $table->string('order_number');
            $table->foreignId('reader_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['order_number', 'reader_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_notified_readers');
    }
};
