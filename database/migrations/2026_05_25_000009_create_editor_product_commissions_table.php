<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editor_product_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('editor_profile_id');
            $table->unsignedInteger('woo_product_id');
            $table->string('product_label');
            $table->boolean('commission_enabled')->default(true);
            // Flat dollar commission per occurrence. Null = use global rate_editor_commission %.
            $table->decimal('custom_amount', 10, 2)->nullable();
            $table->timestamps();

            $table->foreign('editor_profile_id')->references('id')->on('editor_profiles')->cascadeOnDelete();
            $table->unique(['editor_profile_id', 'woo_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editor_product_commissions');
    }
};
