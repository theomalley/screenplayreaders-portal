<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('assignments')->nullOnDelete();
            $table->string('invoice_number', 100);
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['draft', 'sent', 'paid', 'void'])->default('draft');
            $table->enum('invoice_type', ['pdf', 'stripe']);
            $table->string('stripe_invoice_id')->nullable();
            $table->string('stripe_invoice_url', 500)->nullable();
            $table->string('google_doc_id')->nullable();
            $table->string('helpscout_conversation_id')->nullable();
            $table->text('notes')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
