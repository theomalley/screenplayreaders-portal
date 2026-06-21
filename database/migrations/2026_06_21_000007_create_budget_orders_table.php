<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_orders', function (Blueprint $table) {
            $table->id();
            $table->string('woo_order_id', 64)->nullable();
            $table->string('customer_name', 255);
            $table->string('customer_email', 255);
            $table->string('form_entry_id', 64)->nullable();
            $table->decimal('budget_amount', 14, 2);
            $table->unsignedTinyInteger('budget_class');
            $table->string('state', 50)->nullable();
            $table->boolean('guild_wga')->default(false);
            $table->boolean('guild_dga')->default(false);
            $table->boolean('guild_sag')->default(false);
            $table->boolean('guild_iatse')->default(false);
            $table->boolean('guild_teamsters')->default(false);
            $table->boolean('sag_student')->default(false);
            $table->boolean('sag_short')->default(false);
            $table->decimal('weeks_prep', 4, 1)->default(0);
            $table->decimal('weeks_shoot', 4, 1)->default(0);
            $table->decimal('weeks_wrap', 4, 1)->default(0);
            $table->decimal('weeks_post', 4, 1)->default(0);
            $table->boolean('use_time_defaults')->default(true);
            $table->unsignedInteger('cast_size')->default(0);
            $table->json('cast_data')->nullable();
            $table->decimal('surplus_cast', 4, 1)->default(0);
            $table->decimal('surplus_stunts', 4, 1)->default(0);
            $table->decimal('surplus_travel', 4, 1)->default(0);
            $table->decimal('surplus_spfx', 4, 1)->default(0);
            $table->decimal('surplus_mufx', 4, 1)->default(0);
            $table->decimal('surplus_animals', 4, 1)->default(0);
            $table->decimal('surplus_vfx', 4, 1)->default(0);
            $table->json('header_data')->nullable();
            $table->json('form_input_data')->nullable();
            $table->json('payload_json')->nullable();
            $table->boolean('topsheet_only')->default(false);
            $table->string('drive_spreadsheet_id')->nullable();
            $table->string('drive_pdf_id')->nullable();
            $table->string('drive_xlsx_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_orders');
    }
};
