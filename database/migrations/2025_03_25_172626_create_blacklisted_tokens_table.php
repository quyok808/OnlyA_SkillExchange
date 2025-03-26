<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blacklisted_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('token')->unique();
            $table->timestamps(); // Sử dụng timestamps() thay vì chỉ created_at để có updated_at
        });

        // Tạo index cho trường created_at
        DB::statement('ALTER TABLE blacklisted_tokens ADD INDEX blacklisted_tokens_created_at_index (created_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blacklisted_tokens');
    }
};
