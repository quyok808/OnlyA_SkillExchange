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
            // Khóa chính tự tăng
            $table->id();

            // Người báo cáo (Sử dụng UUID vì User model dùng UUID)
            $table->foreignUuid('userId') // <<< Đặt tên cột khớp DB của bạn
                  ->comment('ID người dùng tạo báo cáo (reporter)')
                  ->constrained('users') // Liên kết với bảng 'users', cột 'id'
                  ->cascadeOnDelete(); // Xóa report nếu user bị xóa

            // Người bị báo cáo (Sử dụng UUID)
            $table->foreignUuid('reportedBy') // <<< Đặt tên cột khớp DB của bạn
                  ->comment('ID người dùng bị báo cáo')
                  ->constrained('users') // Liên kết với bảng 'users', cột 'id'
                  ->cascadeOnDelete(); // Xóa report nếu user bị xóa

            // Lý do và trạng thái
            $table->string('reason');
            // $table->text('details')->nullable(); // BỎ CỘT NÀY VÌ DB BẠN KHÔNG CÓ
            $table->string('status')->default('pending')->comment('Trạng thái xử lý');

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('userId');       // Index cho người báo cáo
            $table->index('reportedBy');   // Index cho người bị báo cáo
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
