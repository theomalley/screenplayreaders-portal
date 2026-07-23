<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->string('order_number');
            $table->string('script_title');
            $table->char('author_first_initial', 1);
            $table->string('author_last_name');
            $table->unsignedSmallInteger('page_count');
            $table->foreignId('requested_reader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('rush')->default(false);
            $table->decimal('pay_rate', 8, 2);
            $table->text('notes')->nullable();

            // NOTE: status is a plain string (not enum()) on non-MySQL connections (e.g. the
            // sqlite connection used by the test suite). MySQL gets a real ENUM column as
            // before; later migrations widen it with raw MySQL-only ALTER TABLE ... MODIFY
            // COLUMN statements that don't run on sqlite (see those migrations), so a plain
            // string column there needs no widening — it already accepts any value the app
            // writes. Allowed values are enforced at the application layer either way
            // (Assignment::STATUS_* constants), never solely by the DB column type.
            if (DB::getDriverName() === 'mysql') {
                $table->enum('status', [
                    'incoming',
                    'unassigned',
                    'assigned',
                    'completed',
                    'qc',
                    'cancelled',
                    'on_hold',
                ])->default('incoming');
            } else {
                $table->string('status')->default('incoming');
            }
            $table->string('drive_script_file_id')->nullable();
            $table->string('drive_coverage_doc_id')->nullable();
            $table->string('drive_coverage_pdf_id')->nullable();
            $table->foreignId('assigned_reader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('public_opt_in')->default(false);
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
