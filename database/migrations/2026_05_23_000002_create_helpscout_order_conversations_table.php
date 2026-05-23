<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpscout_order_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('helpscout_conversation_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpscout_order_conversations');
    }
};
