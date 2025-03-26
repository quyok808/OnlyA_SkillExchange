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
        Schema::create('chat_room_participants', function (Blueprint $table) {
            $table->foreignUuid('chatRoomId')->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignUuid('userId')->constrained('users')->cascadeOnDelete();
            $table->primary(['chatRoomId', 'userId']); // Khóa chính composite
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_room_participants');
    }
};
