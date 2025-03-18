<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('initiator_id');
            $table->string('initiator_type');
            $table->unsignedBigInteger('receiver_id');
            $table->string('receiver_type');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            
            // Prevent duplicate conversations
            $table->unique(['initiator_id', 'initiator_type', 'receiver_id', 'receiver_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};