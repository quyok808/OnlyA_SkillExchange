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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Sử dụng UUID làm khóa chính
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable()->unique(); // phone có thể null
            $table->string('address')->nullable(); // address có thể null
            $table->string('password');
            $table->enum('role', ['user', 'admin'])->default('user');
            $table->string('photo')->default('default.jpg');
            $table->boolean('active')->default(false);
            $table->boolean('lock')->default(false);
            $table->string('passwordResetToken')->nullable();
            $table->dateTime('passwordResetExpires')->nullable();
            $table->string('emailVerificationToken')->nullable();
            $table->dateTime('emailVerificationExpires')->nullable();
            $table->dateTime('passwordChangedAt')->nullable();
            $table->timestamps();  // Thêm created_at và updated_at
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
