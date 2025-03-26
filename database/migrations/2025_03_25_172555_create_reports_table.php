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
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('status', ['Processing', 'Completed', 'Canceled', 'Banned', 'Warning', 'Warned'])->default('Processing');
            $table->text('reason');
            $table->foreignUuid('reportedBy')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('userId')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
