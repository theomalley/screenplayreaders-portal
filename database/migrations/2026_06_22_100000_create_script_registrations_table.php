<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('script_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('woo_order_id', 64);
            $table->string('woo_order_number', 64)->nullable();
            $table->string('customer_name', 255);
            $table->string('customer_email', 255);
            $table->unsignedInteger('variation_id');
            $table->string('variation_label', 20);
            $table->string('registration_id', 20)->unique();
            $table->string('script_title', 255);
            $table->unsignedInteger('page_count')->nullable();
            $table->string('type_of_work', 120);
            $table->string('author_first', 120);
            $table->string('author_last', 120);
            $table->string('additional_authors', 2000)->nullable();
            $table->string('street_address', 200);
            $table->string('city', 120);
            $table->string('state_or_province', 120);
            $table->string('postal_or_zip', 40);
            $table->string('country', 120);
            $table->string('phone', 60);
            $table->string('unique_id', 120)->nullable();
            $table->string('email', 255);
            $table->string('uploaded_file_url', 500)->nullable();
            $table->string('uploaded_file_name', 255)->nullable();
            $table->string('authcode', 64);
            $table->timestamp('registered_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('drive_certificate_pdf_id', 255)->nullable();
            $table->string('unlimited_token', 64)->nullable()->unique();
            $table->unsignedBigInteger('unlimited_token_parent_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('woo_order_id');
            $table->index('customer_email');
            $table->foreign('unlimited_token_parent_id')
                ->references('id')
                ->on('script_registrations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('script_registrations');
    }
};
