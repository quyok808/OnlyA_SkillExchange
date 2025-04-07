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
        Schema::table('reports', function (Blueprint $table) {
            // Thay đổi kiểu dữ liệu của cột status thành string (VARCHAR) với độ dài đủ lớn
            // Lưu ý: Lệnh change() yêu cầu cài đặt gói 'doctrine/dbal': composer require doctrine/dbal
            $table->string('status', 255)->default('pending')->change(); // Độ dài 255 thường là đủ
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // Khôi phục lại kiểu dữ liệu cũ nếu cần (thay đổi cho phù hợp)
            // Ví dụ, nếu trước đó là VARCHAR(50)
            // $table->string('status', 50)->default('pending')->change();
        });
    }
};