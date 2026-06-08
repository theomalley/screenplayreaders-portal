<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_link_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_site_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_at');
            $table->boolean('is_up');           // true = at least one qualifying link found
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            // JSON array of [{href, anchor_text, rel, is_dofollow, has_sponsored, has_ugc}]
            $table->json('links_found')->nullable();
            $table->text('error_message')->nullable();
            $table->index(['partner_site_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_link_checks');
    }
};
