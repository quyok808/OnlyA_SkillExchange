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
        Schema::create('user_skills', function (Blueprint $table) {
            $table->foreignUuid('userId')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('skillId')->constrained('skills')->cascadeOnDelete();
            $table->primary(['userId', 'skillId']); // Khóa chính composite
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_skills');
    }
};
