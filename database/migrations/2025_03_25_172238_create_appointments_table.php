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
        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('senderId')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('receiverId')->constrained('users')->cascadeOnDelete();
            $table->dateTime('startTime');
            $table->dateTime('endTime');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'canceled', 'completed'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
