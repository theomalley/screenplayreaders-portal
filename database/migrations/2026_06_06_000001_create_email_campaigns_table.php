<?php

// v1.0 — 2026-06-06 | Marketing email campaigns — replaces Google Sheets + Zapier flow

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_name');
            $table->enum('status', ['draft', 'queued', 'sent', 'paused'])->default('draft');
            $table->unsignedInteger('send_order')->default(0);
            $table->dateTime('scheduled_at')->nullable();

            // Email content
            $table->string('subject_line')->default('');
            $table->string('preheader', 500)->default('');
            $table->string('headline_top')->default('');
            $table->text('paragraph_top1')->nullable();
            $table->text('paragraph_top2')->nullable();
            $table->string('url1', 500)->default('');
            $table->string('headline_bottom')->default('');
            $table->text('paragraph_bottom')->nullable();

            // Promo image (upload path + resolved public URL)
            $table->string('image_path', 500)->nullable();
            $table->string('image_url', 500)->nullable();

            // Coupon
            $table->string('coupon_code', 100)->nullable();
            $table->decimal('coupon_amount', 8, 2)->nullable();
            $table->unsignedInteger('coupon_duration_days')->nullable();
            $table->enum('coupon_type', ['percent', 'fixed_cart'])->nullable();
            $table->json('coupon_product_ids')->nullable();  // array of WC product IDs

            // MailerLite
            $table->string('mailerlite_group_id', 100)->nullable();
            $table->string('mailerlite_campaign_id', 100)->nullable();

            // WooCommerce
            $table->unsignedBigInteger('woo_coupon_id')->nullable();

            // Tracking
            $table->dateTime('test_sent_at')->nullable();
            $table->dateTime('live_sent_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaigns');
    }
};
